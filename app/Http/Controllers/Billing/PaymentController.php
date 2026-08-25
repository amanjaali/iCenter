<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BusCompany;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Accounting\PostingException;
use App\Services\Billing\PaymentService;
use App\Services\SettingsService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Scope of work §4.3 — tracking invoiced, collected and outstanding amounts.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('view-financials');

        $query = Payment::query()
            ->with(['busCompany', 'depositAccount', 'allocations.invoice'])
            ->when($request->integer('bus_company_id'), fn ($q, $id) => $q->where('bus_company_id', $id))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('number', 'like', "%{$term}%")->orWhere('reference', 'like', "%{$term}%")
            ))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('payment_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('payment_date', '<=', $request->date('to')))
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        $totals = (clone $query)->reorder()->selectRaw(
            'COUNT(*) as count, SUM(amount) as received, SUM(unallocated_amount) as unallocated'
        )->first();

        return view('billing.payments.index', [
            'payments' => $query->paginate(30)->withQueryString(),
            'companies' => BusCompany::orderBy('name')->get(),
            'totals' => $totals,
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('manage-payments');

        $company = $request->integer('bus_company_id')
            ? BusCompany::find($request->integer('bus_company_id'))
            : null;

        $invoice = $request->integer('invoice_id') ? Invoice::find($request->integer('invoice_id')) : null;
        $company ??= $invoice?->busCompany;

        return view('billing.payments.form', [
            'companies' => BusCompany::active()->orderBy('name')->get(),
            'company' => $company,
            'accounts' => Account::where('is_cash_equivalent', true)->postable()->orderBy('code')->get(),
            'outstanding' => $company
                ? Invoice::where('bus_company_id', $company->id)->outstanding()->orderBy('due_date')->get()
                : collect(),
            'preselectedInvoice' => $invoice,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-payments');

        $data = $request->validate([
            'bus_company_id' => ['required', 'exists:bus_companies,id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'deposit_account_id' => ['required', 'exists:accounts,id'],
            'method' => ['required', 'in:cash,bank,cheque,transfer'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required_with:allocations.*.amount', 'integer', 'exists:invoices,id'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $allocations = collect($data['allocations'] ?? [])
            ->filter(fn ($a) => (float) ($a['amount'] ?? 0) > 0)
            ->map(fn ($a) => ['invoice_id' => (int) $a['invoice_id'], 'amount' => (float) $a['amount']])
            ->values()
            ->all();

        try {
            $payment = $this->payments->record(
                BusCompany::findOrFail($data['bus_company_id']),
                Carbon::parse($data['payment_date']),
                (float) $data['amount'],
                Account::findOrFail($data['deposit_account_id']),
                $allocations,
                $data['method'],
                $data['reference'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (PostingException $e) {
            return back()->withInput()->withErrors(['posting' => $e->getMessage()]);
        }

        $message = "Receipt {$payment->number} recorded for ".Money::format($payment->amount, true).'.';

        if ((float) $payment->unallocated_amount > 0) {
            $message .= ' '.Money::format($payment->unallocated_amount, true)
                .' is unallocated and can be applied to later invoices.';
        }

        return redirect()->route('payments.show', $payment)->with('success', $message);
    }

    public function show(Payment $payment)
    {
        $this->authorize('view-financials');

        $payment->load(['busCompany', 'depositAccount', 'journal.lines.account', 'allocations.invoice']);

        return view('billing.payments.show', [
            'payment' => $payment,
            'outstanding' => Invoice::where('bus_company_id', $payment->bus_company_id)
                ->outstanding()->orderBy('due_date')->get(),
        ]);
    }

    /**
     * The receipt as it is handed to the bus company — the same stationery as
     * the invoice, listing which invoices the money settled and what, if
     * anything, is still owed after it.
     */
    public function print(Payment $payment)
    {
        $this->authorize('view-financials');

        $payment->load(['busCompany', 'depositAccount', 'allocations.invoice']);

        return view('billing.payments.print', [
            'payment' => $payment,
            'company' => $this->settings->all(),
            'balance' => Invoice::where('bus_company_id', $payment->bus_company_id)
                ->outstanding()->sum('balance_due'),
        ]);
    }

    /** Apply unallocated cash from an advance payment to further invoices. */
    public function allocate(Request $request, Payment $payment)
    {
        $this->authorize('manage-payments');

        $data = $request->validate([
            'allocations' => ['required', 'array'],
            'allocations.*.invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $allocations = collect($data['allocations'])
            ->filter(fn ($a) => (float) ($a['amount'] ?? 0) > 0)
            ->map(fn ($a) => ['invoice_id' => (int) $a['invoice_id'], 'amount' => (float) $a['amount']])
            ->values()
            ->all();

        if ($allocations === []) {
            return back()->with('warning', 'Nothing was allocated.');
        }

        try {
            $this->payments->allocate($payment, $allocations);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', 'The receipt has been applied.');
    }

    /** Suggest an oldest-first allocation for a given amount. */
    public function suggest(Request $request)
    {
        $this->authorize('manage-payments');

        $data = $request->validate([
            'bus_company_id' => ['required', 'exists:bus_companies,id'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $suggestions = $this->payments->suggestAllocation(
            BusCompany::findOrFail($data['bus_company_id']),
            (float) $data['amount'],
        );

        return response()->json(
            collect($suggestions)->map(fn (array $s) => [
                'invoice_id' => $s['invoice']->id,
                'number' => $s['invoice']->number,
                'amount' => $s['amount'],
            ])
        );
    }

    public function void(Request $request, Payment $payment)
    {
        $this->authorize('manage-payments');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->payments->void($payment, $data['reason']);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "Receipt {$payment->number} has been voided and its journal reversed.");
    }

    /** Write an uncollectable balance off to 9080. */
    public function writeOff(Request $request, Invoice $invoice)
    {
        $this->authorize('manage-payments');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'date' => ['nullable', 'date'],
        ]);

        try {
            $this->payments->writeOff(
                $invoice,
                $data['reason'],
                isset($data['date']) ? Carbon::parse($data['date']) : null,
            );
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "Invoice {$invoice->number} has been written off to bad debt.");
    }
}
