<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\BusCompany;
use App\Models\StudentEnrollment;
use Illuminate\Http\Request;

/**
 * Scope of work §3.1 — "Every bus is recorded in the system, together with the
 * number of students assigned to it."
 */
class BusController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-registry');

        $buses = Bus::query()
            ->with('busCompany')
            ->withCount(['students' => fn ($q) => $q->where('status', 'active')])
            ->when($request->integer('bus_company_id'), fn ($q, $id) => $q->where('bus_company_id', $id))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('code', 'like', "%{$term}%")
                    ->orWhere('plate_number', 'like', "%{$term}%")
                    ->orWhere('route_name', 'like', "%{$term}%")
                    ->orWhere('driver_name', 'like', "%{$term}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('bus_company_id')
            ->orderBy('code')
            ->paginate(30)
            ->withQueryString();

        return view('registry.buses.index', [
            'buses' => $buses,
            'companies' => BusCompany::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('manage-registry');

        return view('registry.buses.form', [
            'bus' => new Bus(['status' => 'active', 'bus_company_id' => $request->integer('bus_company_id') ?: null]),
            'companies' => BusCompany::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-registry');

        $bus = Bus::create($this->validated($request));

        return redirect()->route('buses.show', $bus)->with('success', "Bus {$bus->code} has been registered.");
    }

    public function show(Bus $bus)
    {
        $this->authorize('view-registry');

        $bus->load('busCompany');

        return view('registry.buses.show', [
            'bus' => $bus,
            'students' => $bus->students()->orderBy('name')->paginate(30),
            'currentStudents' => StudentEnrollment::where('bus_id', $bus->id)
                ->billable()
                ->overlapping(now()->startOfMonth(), now()->endOfMonth())
                ->distinct()->count('student_id'),
        ]);
    }

    public function edit(Bus $bus)
    {
        $this->authorize('manage-registry');

        return view('registry.buses.form', [
            'bus' => $bus,
            'companies' => BusCompany::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Bus $bus)
    {
        $this->authorize('manage-registry');

        $bus->update($this->validated($request, $bus));

        return redirect()->route('buses.show', $bus)->with('success', "Bus {$bus->code} has been updated.");
    }

    public function destroy(Bus $bus)
    {
        $this->authorize('manage-registry');

        if ($bus->students()->exists() || $bus->enrollments()->exists()) {
            $bus->update(['status' => 'retired']);

            return redirect()->route('buses.index')
                ->with('warning', "Bus {$bus->code} has students on file, so it has been retired rather than deleted.");
        }

        $code = $bus->code;
        $bus->delete();

        return redirect()->route('buses.index')->with('success', "Bus {$code} has been removed.");
    }

    private function validated(Request $request, ?Bus $bus = null): array
    {
        return $request->validate([
            'bus_company_id' => ['required', 'exists:bus_companies,id'],
            'code' => ['required', 'string', 'max:40'],
            'plate_number' => ['nullable', 'string', 'max:40'],
            'capacity' => ['required', 'integer', 'min:0', 'max:200'],
            'driver_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:32'],
            'route_name' => ['nullable', 'string', 'max:255'],
            'device_serial' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'in:active,maintenance,retired'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
