<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\BusCompany;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Scope of work §3.1 — "Each student record is linked to a bus, and each bus is
 * linked to a bus company."
 *
 * Every change that affects what a student is billed for goes through the
 * enrollment history, not through the student row: billing counts days from
 * enrollments, so a transfer or an exit recorded only on the student would be
 * invisible to the invoice.
 */
class StudentController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-registry');

        $students = Student::query()
            ->with(['busCompany', 'bus'])
            ->when($request->integer('bus_company_id'), fn ($q, $id) => $q->where('bus_company_id', $id))
            ->when($request->integer('bus_id'), fn ($q, $id) => $q->where('bus_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('guardian_name', 'like', "%{$term}%")
                    ->orWhere('guardian_phone', 'like', "%{$term}%")
                    ->orWhere('school_name', 'like', "%{$term}%")
            ))
            ->orderBy('name')
            ->paginate(40)
            ->withQueryString();

        return view('registry.students.index', [
            'students' => $students,
            'companies' => BusCompany::orderBy('name')->get(),
            'buses' => $request->integer('bus_company_id')
                ? Bus::where('bus_company_id', $request->integer('bus_company_id'))->orderBy('code')->get()
                : collect(),
            'counts' => [
                'active' => Student::where('status', 'active')->count(),
                'left' => Student::where('status', 'left')->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('manage-registry');

        return view('registry.students.form', [
            'student' => new Student([
                'status' => 'active',
                'joined_on' => now()->toDateString(),
                'bus_company_id' => $request->integer('bus_company_id') ?: null,
                'bus_id' => $request->integer('bus_id') ?: null,
            ]),
            'companies' => BusCompany::active()->orderBy('name')->get(),
            'buses' => Bus::active()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-registry');

        $data = $this->validated($request);
        $this->assertBusBelongsToCompany($data);

        $student = DB::transaction(function () use ($data) {
            $student = Student::create($data);

            // The first enrollment: without it the student is invisible to
            // billing, however complete their record looks.
            StudentEnrollment::create([
                'student_id' => $student->id,
                'bus_id' => $student->bus_id,
                'bus_company_id' => $student->bus_company_id,
                'start_date' => $student->joined_on,
                'start_reason' => 'enrolled',
                'is_billable' => true,
                'created_by' => Auth::id(),
            ]);

            return $student;
        });

        return redirect()->route('students.show', $student)
            ->with('success', "{$student->name} has been enrolled.");
    }

    public function show(Student $student)
    {
        $this->authorize('view-registry');

        $student->load(['busCompany', 'bus', 'enrollments.bus']);

        return view('registry.students.show', [
            'student' => $student,
            'billedMonths' => Auth::user()->can('view-financials')
                ? $student->invoiceDetails()->with('line.invoice')->get()
                    ->sortByDesc(fn ($d) => $d->line?->invoice?->billing_month)
                : collect(),
        ]);
    }

    public function edit(Student $student)
    {
        $this->authorize('manage-registry');

        return view('registry.students.form', [
            'student' => $student,
            'companies' => BusCompany::orderBy('name')->get(),
            'buses' => Bus::where('bus_company_id', $student->bus_company_id)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Student $student)
    {
        $this->authorize('manage-registry');

        $data = $this->validated($request, $student);
        $this->assertBusBelongsToCompany($data);

        DB::transaction(function () use ($student, $data) {
            $movedBus = (int) $data['bus_id'] !== (int) $student->bus_id;
            $student->update($data);

            // A bus change is a billable event: close the open enrollment and
            // open a new one, so each invoice still reproduces exactly.
            if ($movedBus && $open = $student->currentEnrollment()) {
                $changeDate = Carbon::today();

                if ($open->start_date->lt($changeDate)) {
                    $open->update([
                        'end_date' => $changeDate->copy()->subDay(),
                        'end_reason' => 'transferred',
                    ]);

                    StudentEnrollment::create([
                        'student_id' => $student->id,
                        'bus_id' => $student->bus_id,
                        'bus_company_id' => $student->bus_company_id,
                        'start_date' => $changeDate,
                        'start_reason' => 'transferred',
                        'is_billable' => true,
                        'created_by' => Auth::id(),
                    ]);
                } else {
                    // Same day as it started: correct the row rather than
                    // leaving a zero-day enrollment behind.
                    $open->update(['bus_id' => $student->bus_id]);
                }
            }
        });

        return redirect()->route('students.show', $student)
            ->with('success', "{$student->name} has been updated.");
    }

    /** Record a student leaving, closing the enrollment on a chosen date. */
    public function withdraw(Request $request, Student $student)
    {
        $this->authorize('manage-registry');

        $data = $request->validate([
            'left_on' => ['required', 'date'],
            'end_reason' => ['required', 'string', 'max:60'],
        ]);

        $open = $student->currentEnrollment();

        if (! $open) {
            return back()->with('warning', "{$student->name} has no open enrollment to close.");
        }

        $leftOn = Carbon::parse($data['left_on']);

        if ($leftOn->lt($open->start_date)) {
            throw ValidationException::withMessages([
                'left_on' => 'A student cannot leave before they joined ('.$open->start_date->format('j M Y').').',
            ]);
        }

        DB::transaction(function () use ($student, $open, $leftOn, $data) {
            $open->update(['end_date' => $leftOn, 'end_reason' => $data['end_reason']]);
            $student->update(['left_on' => $leftOn, 'status' => 'left']);
        });

        return redirect()->route('students.show', $student)
            ->with('success', "{$student->name} left on {$leftOn->format('j M Y')}. Billing stops from that date.");
    }

    /** Re-enrol a student who has returned. */
    public function reinstate(Request $request, Student $student)
    {
        $this->authorize('manage-registry');

        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'bus_id' => ['required', 'exists:buses,id'],
        ]);

        if ($student->currentEnrollment()) {
            return back()->with('warning', "{$student->name} is already enrolled.");
        }

        DB::transaction(function () use ($student, $data) {
            StudentEnrollment::create([
                'student_id' => $student->id,
                'bus_id' => $data['bus_id'],
                'bus_company_id' => $student->bus_company_id,
                'start_date' => $data['start_date'],
                'start_reason' => 'resumed',
                'is_billable' => true,
                'created_by' => Auth::id(),
            ]);

            $student->update(['bus_id' => $data['bus_id'], 'left_on' => null, 'status' => 'active']);
        });

        return redirect()->route('students.show', $student)
            ->with('success', "{$student->name} has been re-enrolled.");
    }

    public function destroy(Student $student)
    {
        $this->authorize('manage-registry');

        // A student who has ever been billed is never deleted: the invoice
        // detail points at them and §9.3 forbids losing the trail.
        if ($student->invoiceDetails()->exists()) {
            return back()->with('error', "{$student->name} appears on issued invoices and cannot be deleted. Record them as having left instead.");
        }

        $name = $student->name;
        $student->delete();

        return redirect()->route('students.index')->with('success', "{$name} has been removed.");
    }

    private function assertBusBelongsToCompany(array $data): void
    {
        if (empty($data['bus_id'])) {
            return;
        }

        $bus = Bus::find($data['bus_id']);

        if ($bus && (int) $bus->bus_company_id !== (int) $data['bus_company_id']) {
            throw ValidationException::withMessages([
                'bus_id' => 'That bus belongs to a different bus company.',
            ]);
        }
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'bus_company_id' => ['required', 'exists:bus_companies,id'],
            'bus_id' => ['nullable', 'exists:buses,id'],
            'code' => ['required', 'string', 'max:40', 'unique:students,code'.($student ? ",{$student->id}" : '')],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:32'],
            'school_name' => ['nullable', 'string', 'max:255'],
            'grade' => ['nullable', 'string', 'max:40'],
            'ble_tag' => ['nullable', 'string', 'max:60'],
            'joined_on' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive,left'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
