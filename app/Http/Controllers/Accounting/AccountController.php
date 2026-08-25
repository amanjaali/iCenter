<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Accounting\LedgerService;
use App\Services\Reports\ReportFilters;
use Illuminate\Http\Request;

/**
 * Scope of work §6 — the chart of accounts. Sub-accounts may be added inside a
 * class; the class numbering is fixed, so a new code must sit in a real class.
 */
class AccountController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index(Request $request)
    {
        $this->authorize('view-financials');

        $filters = ReportFilters::fromRequest($request->all());

        $accounts = Account::query()
            ->when($request->integer('class'), fn ($q, $class) => $q->where('class', $class))
            ->when($request->string('type')->toString(), fn ($q, $t) => $q->where('type', $t))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(
                fn ($w) => $w->where('code', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('name_ar', 'like', "%{$term}%")
            ))
            ->orderBy('code')
            ->get();

        // Balances for the filtered window, so the chart doubles as a summary.
        $movements = $this->ledger->movements($filters->from, $filters->to);

        return view('accounting.accounts.index', [
            'accounts' => $accounts,
            'movements' => $movements,
            'filters' => $filters,
            'classes' => [
                1000 => 'Assets', 2000 => 'Liabilities', 3000 => 'Equity', 4000 => 'Revenue',
                5000 => 'Direct costs', 6000 => 'Payroll', 7000 => 'Office and administrative',
                8000 => 'Vehicle', 9000 => 'Sales, marketing and other',
            ],
        ]);
    }

    public function show(Account $account, Request $request)
    {
        $this->authorize('view-financials');

        $filters = ReportFilters::fromRequest($request->all());

        return view('accounting.accounts.show', [
            'account' => $account,
            'filters' => $filters,
            'ledger' => $this->ledger->generalLedger($account, $filters->from, $filters->to, $filters->ledgerFilters()),
        ]);
    }

    public function create()
    {
        $this->authorize('manage-chart-of-accounts');

        return view('accounting.accounts.form', [
            'account' => new Account(['is_postable' => true, 'is_active' => true]),
            'parents' => Account::orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage-chart-of-accounts');

        $data = $this->validated($request);
        $type = AccountType::fromCode($data['code']);

        $account = Account::create($data + [
            'type' => $type,
            'normal_balance' => $data['normal_balance'] ?? $type->normalBalance()->value,
            'class' => ((int) substr($data['code'], 0, 1)) * 1000,
            'is_system' => false,
        ]);

        return redirect()->route('accounts.index')
            ->with('success', "Account {$account->displayName()} has been added.");
    }

    public function edit(Account $account)
    {
        $this->authorize('manage-chart-of-accounts');

        return view('accounting.accounts.form', [
            'account' => $account,
            'parents' => Account::where('id', '!=', $account->id)->orderBy('code')->get(),
        ]);
    }

    public function update(Request $request, Account $account)
    {
        $this->authorize('manage-chart-of-accounts');

        $data = $this->validated($request, $account);

        // System accounts are resolved by code by the posting engine, so their
        // code is not editable — renumbering one would send automatic journals
        // to the wrong place.
        if ($account->is_system) {
            unset($data['code']);
        }

        $account->update($data);

        return redirect()->route('accounts.index')
            ->with('success', "Account {$account->displayName()} has been updated.");
    }

    private function validated(Request $request, ?Account $account = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[1-9]\d{3,}$/',
                'unique:accounts,code'.($account ? ",{$account->id}" : ''),
            ],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'subtype' => ['nullable', 'string', 'max:40'],
            'normal_balance' => ['nullable', 'in:debit,credit'],
            'parent_id' => ['nullable', 'exists:accounts,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_postable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'default_nature' => ['nullable', 'in:fixed,variable'],
            'requires_department' => ['nullable', 'boolean'],
            'requires_project' => ['nullable', 'boolean'],
            'is_cash_equivalent' => ['nullable', 'boolean'],
        ], [
            'code.regex' => 'An account code must be four or more digits inside an existing class (1000 to 9000).',
        ]);
    }
}
