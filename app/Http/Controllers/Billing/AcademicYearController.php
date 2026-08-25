<?php

namespace App\Http\Controllers\Billing;

use App\Enums\BillingMode;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Scope of work §10.3 — "Does billing pause during school holidays and the
 * summer, or continue through the full twelve months? This must be set per
 * academic year."
 */
class AcademicYearController extends Controller
{
    public function index()
    {
        $this->authorize('view-rates');

        return view('billing.years.index', [
            'years' => AcademicYear::withCount(['rateCards', 'invoices'])
                ->orderByDesc('start_date')->get(),
        ]);
    }

    public function create()
    {
        $this->authorize('manage-rates');

        $latest = AcademicYear::orderByDesc('start_date')->first();
        $nextStart = $latest ? $latest->end_date->copy()->addDay() : now()->startOfYear()->setMonth(9)->startOfMonth();

        return view('billing.years.form', [
            'year' => new AcademicYear([
                'name' => $nextStart->year.'-'.($nextStart->year + 1),
                'start_date' => $nextStart->toDateString(),
                'end_date' => $nextStart->copy()->addYear()->subDay()->toDateString(),
                'billing_mode' => app(SettingsService::class)->get('billing.default_billing_mode', 'continue'),
                'billable_months' => [9, 10, 11, 12, 1, 2, 3, 4, 5, 6],
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-rates');

        $year = $this->persist(new AcademicYear, $request);

        return redirect()->route('academic-years.index')
            ->with('success', "Academic year {$year->name} has been created.");
    }

    public function edit(AcademicYear $academicYear)
    {
        $this->authorize('manage-rates');

        return view('billing.years.form', ['year' => $academicYear]);
    }

    public function update(Request $request, AcademicYear $academicYear)
    {
        $this->authorize('manage-rates');

        $this->persist($academicYear, $request);

        return redirect()->route('academic-years.index')
            ->with('success', "Academic year {$academicYear->name} has been updated.");
    }

    private function persist(AcademicYear $year, Request $request): AcademicYear
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'unique:academic_years,name'.($year->exists ? ",{$year->id}" : '')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'billing_mode' => ['required', 'in:continue,pause'],
            'billable_months' => ['nullable', 'array'],
            'billable_months.*' => ['integer', 'min:1', 'max:12'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return DB::transaction(function () use ($year, $data) {
            $year->fill([
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'billing_mode' => $data['billing_mode'],
                // In 'continue' mode the month list is irrelevant; storing it
                // anyway means switching to 'pause' later keeps the choice.
                'billable_months' => $data['billable_months'] ?? [],
                'is_active' => (bool) ($data['is_active'] ?? false),
                'notes' => $data['notes'] ?? null,
            ])->save();

            // Only one year is "current" at a time.
            if ($year->is_active) {
                AcademicYear::where('id', '!=', $year->id)->update(['is_active' => false]);
            }

            return $year;
        });
    }
}
