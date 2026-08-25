<?php

namespace App\Http\Controllers\Billing;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\BusCompany;
use App\Models\Invoice;
use App\Services\Accounting\PostingException;
use App\Services\Billing\BillingService;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\RevenueRecognitionService;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Scope of work §4.3 — the monthly billing cycle.
 */
class InvoiceController extends Controller
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly InvoiceService $invoices,
        private readonly RevenueRecognitionService $recognition,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view-financials');

        $status = $request->string('status')->toString();

        $query = Invoice::query()
            ->with(['busCompany', 'period'])
            ->when($request->integer('bus_company_id'), fn ($q, $id) => $q->where('bus_company_id', $id))
            ->when($request->string('month')->toString(), fn ($q, $m) => $q->whereDate('billing_month', Carbon::parse($m)->startOfMonth()))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('number', 'like', "%{$term}%"))
            ->when($status === 'outstanding', fn ($q) => $q->outstanding())
            ->when($status === 'overdue', fn ($q) => $q->outstanding()->whereDate('due_date', '<', now()))
            ->when($status === 'deferred', fn ($q) => $q->where('is_deferred', true)->where('revenue_recognised', false))
            ->when($status && ! in_array($status, ['outstanding', 'overdue', 'deferred'], true),
                fn ($q) => $q->where('status', $status))
            ->orderByDesc('billing_month')
            ->orderByDesc('id');

        // Totals are for the filtered set, not the current page — a page total
        // that ignores the filter is worse than no total.
        $totals = (clone $query)->reorder()->selectRaw(
            'COUNT(*) as count, SUM(total) as invoiced, SUM(amount_paid) as collected, SUM(balance_due) as outstanding'
        )->first();

        return view('billing.invoices.index', [
            'invoices' => $query->paginate(30)->withQueryString(),
            'companies' => BusCompany::orderBy('name')->get(),
            'totals' => $totals,
            'status' => $status,
            'recognisable' => $this->recognition->due()->count(),
        ]);
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view-financials');

        $invoice->load([
            'busCompany', 'period', 'academicYear', 'journal.lines.account',
            'recognitionJournal', 'lines.bus', 'lines.students.student',
            'allocations.payment',
        ]);

        return view('billing.invoices.show', compact('invoice'));
    }

    /** The month-end generation screen: preview, then commit. */
    public function generate(Request $request)
    {
        $this->authorize('manage-invoices');

        $month = $request->filled('month')
            ? Carbon::parse($request->string('month')->toString())->startOfMonth()
            : now()->startOfMonth();

        $companies = BusCompany::active()->orderBy('name')->get();

        // A dry run for every company, so the accountant sees exactly what will
        // be raised — and why a company is being skipped — before committing.
        $previews = $companies->map(function (BusCompany $company) use ($month) {
            $existing = Invoice::where('bus_company_id', $company->id)->forMonth($month)->first();

            return [
                'company' => $company,
                'existing' => $existing,
                'preview' => $existing ? null : $this->billing->preview($company, $month),
            ];
        });

        return view('billing.invoices.generate', [
            'month' => $month,
            'previews' => $previews,
            'prorationMethod' => $this->settings->prorationMethod(),
            'billableTotal' => Money::round($previews->sum(fn ($row) => $row['preview']['total'] ?? 0)),
            'billableCount' => $previews->filter(fn ($row) => $row['preview']['billable'] ?? false)->count(),
        ]);
    }

    public function runGeneration(Request $request)
    {
        $this->authorize('manage-invoices');

        $data = $request->validate([
            'month' => ['required', 'date'],
            'company_ids' => ['nullable', 'array'],
            'company_ids.*' => ['integer', 'exists:bus_companies,id'],
            'issue_date' => ['nullable', 'date'],
        ]);

        $month = Carbon::parse($data['month'])->startOfMonth();

        try {
            $result = $this->billing->generateMonth(
                $month,
                $data['company_ids'] ?? null,
                isset($data['issue_date']) ? Carbon::parse($data['issue_date']) : null,
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        $created = $result['created']->count();

        if ($created === 0) {
            return back()->with('warning', 'Nothing was billable for '.$month->format('F Y').'.');
        }

        return redirect()->route('invoices.index', ['month' => $month->toDateString()])
            ->with('success', $created.' draft '.str('invoice')->plural($created)
                .' generated for '.$month->format('F Y').', totalling '
                .Money::format($result['created']->sum('total'), true).'. Review and issue them.');
    }

    public function issue(Invoice $invoice)
    {
        $this->authorize('manage-invoices');

        try {
            $this->invoices->issue($invoice);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "Invoice {$invoice->number} has been issued and posted to the ledger.");
    }

    /** Issue every draft for a month in one step. */
    public function issueAll(Request $request)
    {
        $this->authorize('manage-invoices');

        $data = $request->validate(['month' => ['required', 'date']]);
        $month = Carbon::parse($data['month'])->startOfMonth();

        $drafts = Invoice::where('status', DocumentStatus::Draft->value)->forMonth($month)->get();
        $issued = 0;
        $failures = [];

        foreach ($drafts as $draft) {
            try {
                $this->invoices->issue($draft);
                $issued++;
            } catch (\Throwable $e) {
                // One failure must not abandon the rest of the run.
                $failures[] = "{$draft->number}: {$e->getMessage()}";
            }
        }

        return back()
            ->with($issued > 0 ? 'success' : 'warning', $issued.' '.str('invoice')->plural($issued).' issued for '.$month->format('F Y').'.')
            ->with('error', $failures ? implode(' · ', array_slice($failures, 0, 3)) : null);
    }

    public function void(Request $request, Invoice $invoice)
    {
        $this->authorize('manage-invoices');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->invoices->void($invoice, $data['reason']);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "Invoice {$invoice->number} has been voided and its journal reversed.");
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('manage-invoices');

        try {
            $this->invoices->deleteDraft($invoice);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return redirect()->route('invoices.index')->with('success', 'The draft invoice has been deleted.');
    }

    /** Scope of work §4.4 — release deferred revenue whose month has arrived. */
    public function recogniseRevenue()
    {
        $this->authorize('manage-invoices');

        $result = $this->recognition->recogniseDue();

        if ($result['recognised'] === 0) {
            return back()->with('info', 'No deferred revenue is due to be recognised yet.');
        }

        return back()
            ->with('success', $result['recognised'].' '.str('invoice')->plural($result['recognised'])
                .' released from deferred revenue, totalling '.Money::format($result['amount'], true).'.')
            ->with('error', $result['failures'] ? implode(' · ', array_slice($result['failures'], 0, 3)) : null);
    }

    /** A printable invoice on the brand's invoice stationery. */
    public function print(Invoice $invoice)
    {
        $this->authorize('view-financials');

        $invoice->load(['busCompany', 'lines.bus', 'academicYear']);

        return view('billing.invoices.print', [
            'invoice' => $invoice,
            'company' => $this->settings->all(),
        ]);
    }
}
