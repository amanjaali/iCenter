<?php

namespace App\Http\Controllers\Expenses;

use App\Enums\CapitalTreatment;
use App\Enums\ExpenseNature;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Supplier;
use App\Services\Accounting\PostingException;
use App\Services\Expenses\ExpenseService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Scope of work §7 — every expense carries an account, a cost centre and a
 * project, and is classified fixed/variable and capex/opex.
 */
class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request)
    {
        $this->authorize('view-financials');

        $query = Expense::query()
            ->with(['account', 'department', 'project', 'supplier'])
            ->when($request->integer('account_id'), fn ($q, $id) => $q->where('account_id', $id))
            ->when($request->integer('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->integer('project_id'), fn ($q, $id) => $q->where('project_id', $id))
            ->when($request->integer('supplier_id'), fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('nature')->toString(), fn ($q, $n) => $q->where('nature', $n))
            ->when($request->string('treatment')->toString(), fn ($q, $t) => $q->where('treatment', $t))
            ->when($request->string('payment_status')->toString(), fn ($q, $p) => $q->where('payment_status', $p))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->date('to')))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('number', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")
            ))
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        $totals = (clone $query)->reorder()->selectRaw(
            'COUNT(*) as count, SUM(total) as total, SUM(total - amount_paid) as unpaid'
        )->first();

        return view('expenses.index', [
            'expenses' => $query->paginate(30)->withQueryString(),
            'accounts' => Account::inClass(5000, 6000, 7000, 8000, 9000)->postable()->orderBy('code')->get(),
            'departments' => Department::where('is_active', true)->orderBy('sort_order')->get(),
            'projects' => Project::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'totals' => $totals,
        ]);
    }

    public function create()
    {
        $this->authorize('manage-expenses');

        return view('expenses.form', $this->formData() + [
            'expense' => new Expense([
                'expense_date' => now()->toDateString(),
                'nature' => ExpenseNature::Variable,
                'treatment' => CapitalTreatment::Opex,
                'payment_status' => 'paid',
                'project_id' => Project::overheadPool()?->id,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-expenses');

        $data = $this->validated($request);

        try {
            $expense = $this->expenses->create($data);

            if ($request->boolean('post_now')) {
                $this->expenses->post($expense);
            }
        } catch (PostingException $e) {
            return back()->withInput()->withErrors(['posting' => $e->getMessage()]);
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', "Expense {$expense->number} for ".Money::format($expense->total, true)
                .($expense->fresh()->isPosted() ? ' has been posted.' : ' has been saved as a draft.'));
    }

    public function show(Expense $expense)
    {
        $this->authorize('view-financials');

        $expense->load([
            'account', 'department', 'project', 'supplier', 'paidFromAccount',
            'journal.lines.account', 'payments.sourceAccount', 'fixedAsset',
        ]);

        return view('expenses.show', [
            'expense' => $expense,
            'cashAccounts' => Account::where('is_cash_equivalent', true)->postable()->orderBy('code')->get(),
            'depreciationAccounts' => Account::whereIn('code', ['8050', '9060', '9070'])->get(),
        ]);
    }

    public function edit(Expense $expense)
    {
        $this->authorize('manage-expenses');

        if (! $expense->isDraft()) {
            return redirect()->route('expenses.show', $expense)
                ->with('warning', 'A posted expense cannot be edited. Void it and enter a corrected one.');
        }

        return view('expenses.form', $this->formData() + ['expense' => $expense]);
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorize('manage-expenses');

        if (! $expense->isDraft()) {
            return back()->with('error', 'A posted expense cannot be edited.');
        }

        $data = $this->validated($request);
        $amount = Money::round($data['amount']);
        $tax = Money::round($data['tax_amount'] ?? 0);

        $expense->update($data + ['total' => Money::round($amount + $tax)]);

        if ($request->boolean('post_now')) {
            try {
                $this->expenses->post($expense->fresh());
            } catch (PostingException $e) {
                return back()->withErrors(['posting' => $e->getMessage()]);
            }
        }

        return redirect()->route('expenses.show', $expense)->with('success', "Expense {$expense->number} has been updated.");
    }

    public function post(Expense $expense)
    {
        $this->authorize('manage-expenses');

        try {
            $this->expenses->post($expense);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "Expense {$expense->number} has been posted to the ledger.");
    }

    public function void(Request $request, Expense $expense)
    {
        $this->authorize('manage-expenses');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $this->expenses->void($expense, $data['reason']);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', "Expense {$expense->number} has been voided and its journal reversed.");
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('manage-expenses');

        try {
            $this->expenses->deleteDraft($expense);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return redirect()->route('expenses.index')->with('success', 'The draft expense has been deleted.');
    }

    /** Settle an unpaid supplier bill. */
    public function pay(Request $request, Expense $expense)
    {
        $this->authorize('manage-expenses');

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'source_account_id' => ['required', 'exists:accounts,id'],
            'method' => ['required', 'in:cash,bank,cheque,transfer'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        try {
            $this->expenses->paySupplier(
                $expense,
                Carbon::parse($data['payment_date']),
                (float) $data['amount'],
                Account::findOrFail($data['source_account_id']),
                $data['method'],
                $data['reference'] ?? null,
            );
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return back()->with('success', 'The supplier payment has been recorded.');
    }

    /** §7 — capitalise a capex expense into the fixed asset register. */
    public function capitalise(Request $request, Expense $expense)
    {
        $this->authorize('manage-assets');

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:30', 'unique:fixed_assets,code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:vehicle,furniture,it,intangible'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:600'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'depreciation_start_date' => ['nullable', 'date'],
            'depreciation_expense_account_id' => ['nullable', 'exists:accounts,id'],
            'serial_number' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $asset = $this->expenses->capitalise($expense, $data);
        } catch (PostingException $e) {
            return back()->withErrors(['posting' => $e->getMessage()]);
        }

        return redirect()->route('assets.show', $asset)
            ->with('success', "{$asset->name} has been added to the asset register and will now depreciate.");
    }

    private function formData(): array
    {
        return [
            'accounts' => Account::postable()
                ->where(fn ($q) => $q->inClass(5000, 6000, 7000, 8000, 9000)
                    ->orWhereIn('code', ['1040', '1060', '1110', '1120', '1130', '1140']))
                ->orderBy('code')->get(),
            'departments' => Department::where('is_active', true)->orderBy('sort_order')->get(),
            'projects' => Project::where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'cashAccounts' => Account::where('is_cash_equivalent', true)->postable()->orderBy('code')->get(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'expense_date' => ['required', 'date'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'account_id' => ['required', 'exists:accounts,id'],
            // §7 makes both tags mandatory, not optional.
            'department_id' => ['required', 'exists:departments,id'],
            'project_id' => ['required', 'exists:projects,id'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'nature' => ['required', 'in:fixed,variable'],
            'treatment' => ['required', 'in:capex,opex'],
            'payment_status' => ['required', 'in:paid,unpaid'],
            'paid_from_account_id' => ['nullable', 'required_if:payment_status,paid', 'exists:accounts,id'],
            'due_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'paid_from_account_id.required_if' => 'Choose the cash or bank account this expense was paid from.',
            'department_id.required' => 'Every expense must carry a cost centre (scope of work §7).',
            'project_id.required' => 'Every expense must carry a project (scope of work §7).',
        ]);
    }
}
