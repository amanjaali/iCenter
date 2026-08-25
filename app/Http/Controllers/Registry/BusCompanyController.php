<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Models\BusCompany;
use App\Models\StudentEnrollment;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\RevenueReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Scope of work §3.1 — "Every bus company must register its buses with us."
 * The top of the chain that feeds billing.
 */
class BusCompanyController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view-registry');

        $companies = BusCompany::query()
            ->withCount([
                'buses' => fn ($q) => $q->where('status', 'active'),
                'students' => fn ($q) => $q->where('status', 'active'),
            ])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhere('contact_name', 'like', "%{$term}%")
            ))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Outstanding balances are only shown to those allowed to see money.
        $balances = Auth::user()->can('view-financials')
            ? BusCompany::whereIn('id', $companies->pluck('id'))
                ->get()
                ->mapWithKeys(fn (BusCompany $c) => [$c->id => $c->outstandingBalance()])
            : collect();

        return view('registry.companies.index', compact('companies', 'balances'));
    }

    public function create()
    {
        $this->authorize('manage-registry');

        return view('registry.companies.form', ['company' => new BusCompany(['status' => 'active', 'payment_terms_days' => 15])]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-registry');

        $company = BusCompany::create($this->validated($request) + ['created_by' => Auth::id()]);

        return redirect()->route('bus-companies.show', $company)
            ->with('success', "{$company->name} has been registered.");
    }

    public function show(BusCompany $busCompany, Request $request)
    {
        $this->authorize('view-registry');

        $busCompany->load(['buses' => fn ($q) => $q->orderBy('code')]);

        // Students per bus, counted from the enrollment history rather than
        // the status column, so the figure matches what billing will charge.
        $studentsPerBus = StudentEnrollment::query()
            ->where('bus_company_id', $busCompany->id)
            ->billable()
            ->overlapping(now()->startOfMonth(), now()->endOfMonth())
            ->selectRaw('bus_id, COUNT(DISTINCT student_id) as total')
            ->groupBy('bus_id')
            ->pluck('total', 'bus_id');

        $statement = null;

        if (Auth::user()->can('view-financials')) {
            $filters = ReportFilters::fromRequest($request->all() + [
                'from' => now()->startOfYear()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]);

            $statement = app(RevenueReportService::class)->busCompanyStatement($busCompany, $filters);
        }

        return view('registry.companies.show', compact('busCompany', 'studentsPerBus', 'statement'));
    }

    public function edit(BusCompany $busCompany)
    {
        $this->authorize('manage-registry');

        return view('registry.companies.form', ['company' => $busCompany]);
    }

    public function update(Request $request, BusCompany $busCompany)
    {
        $this->authorize('manage-registry');

        $busCompany->update($this->validated($request, $busCompany));

        return redirect()->route('bus-companies.show', $busCompany)
            ->with('success', "{$busCompany->name} has been updated.");
    }

    public function destroy(BusCompany $busCompany)
    {
        $this->authorize('manage-registry');

        // A company with history is never deleted — invoices and ledger entries
        // point at it. Suspending it keeps the record intact.
        if ($busCompany->invoices()->exists() || $busCompany->buses()->exists()) {
            $busCompany->update(['status' => 'closed']);

            return redirect()->route('bus-companies.index')
                ->with('warning', "{$busCompany->name} has buses or invoices on file, so it has been closed rather than deleted.");
        }

        $name = $busCompany->name;
        $busCompany->delete();

        return redirect()->route('bus-companies.index')->with('success', "{$name} has been removed.");
    }

    private function validated(Request $request, ?BusCompany $company = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:bus_companies,code'.($company ? ",{$company->id}" : '')],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'tax_number' => ['nullable', 'string', 'max:60'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'contract_start' => ['nullable', 'date'],
            'contract_end' => ['nullable', 'date', 'after_or_equal:contract_start'],
            'status' => ['required', 'in:active,suspended,closed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
