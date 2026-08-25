<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Enums\JournalStatus;
use App\Models\Account;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the ledger. Every financial report is built from these queries,
 * so "what the ledger says" has exactly one definition.
 *
 * Only posted journals are ever included — drafts and reversed entries are
 * invisible to reporting by construction.
 */
class LedgerService
{
    /**
     * Base query over posted journal lines, with the standard filters the
     * reports all share.
     *
     * @param  array{project_id?:int|null, department_id?:int|null, bus_company_id?:int|null, partner_id?:int|null, source_type?:string|null}  $filters
     */
    public function lines(?Carbon $from = null, ?Carbon $to = null, array $filters = []): QueryBuilder
    {
        $query = DB::table('journal_lines as jl')
            ->join('journals as j', 'j.id', '=', 'jl.journal_id')
            ->where('j.status', JournalStatus::Posted->value);

        if ($from) {
            $query->whereDate('j.journal_date', '>=', $from->toDateString());
        }

        if ($to) {
            $query->whereDate('j.journal_date', '<=', $to->toDateString());
        }

        foreach (['project_id', 'department_id', 'bus_company_id', 'partner_id'] as $key) {
            if (! empty($filters[$key])) {
                $query->where("jl.{$key}", $filters[$key]);
            }
        }

        if (! empty($filters['source_type'])) {
            $query->where('j.source_type', $filters['source_type']);
        }

        if (! empty($filters['nature'])) {
            $query->where('jl.nature', $filters['nature']);
        }

        if (! empty($filters['treatment'])) {
            $query->where('jl.treatment', $filters['treatment']);
        }

        return $query;
    }

    /**
     * Movement per account over a window.
     *
     * @return Collection<int,object{account_id:int, debit:float, credit:float}>
     */
    public function movements(?Carbon $from, ?Carbon $to, array $filters = []): Collection
    {
        return $this->lines($from, $to, $filters)
            ->selectRaw('jl.account_id, SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->groupBy('jl.account_id')
            ->get()
            ->map(fn ($row) => (object) [
                'account_id' => (int) $row->account_id,
                'debit' => round((float) $row->debit, 2),
                'credit' => round((float) $row->credit, 2),
            ])
            ->keyBy('account_id');
    }

    /**
     * Net movement of one account (in its own direction) over a window.
     */
    public function accountMovement(Account $account, ?Carbon $from, ?Carbon $to, array $filters = []): float
    {
        $row = $this->lines($from, $to, $filters)
            ->where('jl.account_id', $account->id)
            ->selectRaw('SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->first();

        return $account->signedBalance((float) ($row->debit ?? 0), (float) ($row->credit ?? 0));
    }

    /**
     * Closing balance of an account as at a date.
     *
     * Balance sheet accounts accumulate from the beginning of time; profit and
     * loss accounts accumulate only from the start of their fiscal year, which
     * is what makes "current year profit" mean the current year.
     */
    public function balanceAsAt(Account $account, Carbon $asAt, array $filters = []): float
    {
        $from = $account->type->isProfitAndLoss()
            ? $this->fiscalYearStart($asAt)
            : null;

        return $this->accountMovement($account, $from, $asAt, $filters);
    }

    /**
     * Sum of the signed balances of every account in a set of classes.
     * The workhorse behind the income statement and the balance sheet.
     *
     * @param  array<int>  $classes
     */
    public function classTotal(array $classes, ?Carbon $from, ?Carbon $to, array $filters = []): float
    {
        $row = $this->lines($from, $to, $filters)
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('a.class', $classes)
            ->selectRaw('SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->first();

        $debit = (float) ($row->debit ?? 0);
        $credit = (float) ($row->credit ?? 0);

        // Classes 1000 and 5000-9000 are debit-natured; 2000-4000 are credit.
        $isDebitNatured = ! array_filter($classes, fn (int $c) => ! self::classIsDebitNatured($c));

        return round($isDebitNatured ? $debit - $credit : $credit - $debit, 2);
    }

    /** True when a class's natural side is debit (assets and all cost classes). */
    public static function classIsDebitNatured(int $class): bool
    {
        return in_array($class, [1000, 5000, 6000, 7000, 8000, 9000], true);
    }

    /**
     * Per-account balances for a set of classes, with the account details
     * attached. Used by the income statement, balance sheet and expense report.
     *
     * Each row carries two figures, and the difference matters:
     *
     *  - `balance` is on the account's own normal side, which is how an
     *    individual account is conventionally read.
     *  - `natural` is on the *section's* side, which is what a section total
     *    must sum. A contra account — 1190 accumulated depreciation, 4019
     *    revenue discounts, 3060 partner drawings — has a normal balance
     *    opposite to its section, so summing `balance` would add it where it
     *    should be deducted.
     *
     * @param  array<int>  $classes
     * @return Collection<int,object>
     */
    public function balancesByClass(array $classes, ?Carbon $from, ?Carbon $to, array $filters = []): Collection
    {
        $rows = $this->lines($from, $to, $filters)
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('a.class', $classes)
            ->selectRaw('a.id, a.code, a.name, a.type, a.subtype, a.class, a.normal_balance,
                         SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.subtype', 'a.class', 'a.normal_balance')
            ->orderBy('a.code')
            ->get();

        return $rows->map(function ($row) {
            $debit = round((float) $row->debit, 2);
            $credit = round((float) $row->credit, 2);

            $class = (int) $row->class;

            return (object) [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'subtype' => $row->subtype,
                'class' => $class,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($row->normal_balance === 'debit' ? $debit - $credit : $credit - $debit, 2),
                'natural' => round(self::classIsDebitNatured($class) ? $debit - $credit : $credit - $debit, 2),
                'is_contra' => self::classIsDebitNatured($class) !== ($row->normal_balance === 'debit'),
            ];
        });
    }

    /**
     * Scope of work §8.1 — the trial balance. Debits and credits per account
     * for the window, with the totals that must agree.
     *
     * @return array{rows: Collection, total_debit: float, total_credit: float, balanced: bool}
     */
    public function trialBalance(Carbon $from, Carbon $to, array $filters = []): array
    {
        $rows = $this->lines($from, $to, $filters)
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->selectRaw('a.id, a.code, a.name, a.type, a.class, a.normal_balance,
                         SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.class', 'a.normal_balance')
            ->havingRaw('SUM(jl.debit) <> 0 OR SUM(jl.credit) <> 0')
            ->orderBy('a.code')
            ->get()
            ->map(function ($row) {
                $debit = round((float) $row->debit, 2);
                $credit = round((float) $row->credit, 2);
                $net = round($debit - $credit, 2);

                return (object) [
                    'id' => (int) $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'type' => $row->type,
                    'class' => (int) $row->class,
                    'debit' => $debit,
                    'credit' => $credit,
                    // Presented the conventional way: the net on one side only.
                    'net_debit' => $net > 0 ? $net : 0.0,
                    'net_credit' => $net < 0 ? abs($net) : 0.0,
                ];
            });

        $totalDebit = round($rows->sum('net_debit'), 2);
        $totalCredit = round($rows->sum('net_credit'), 2);

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balanced' => abs($totalDebit - $totalCredit) < 0.005,
        ];
    }

    /**
     * Scope of work §8.1 — general ledger detail for one account, with a
     * running balance carried from the opening position.
     *
     * @return array{opening: float, rows: Collection, closing: float}
     */
    public function generalLedger(Account $account, Carbon $from, Carbon $to, array $filters = []): array
    {
        $opening = $account->type->isProfitAndLoss()
            ? $this->accountMovement($account, $this->fiscalYearStart($from), $from->copy()->subDay(), $filters)
            : $this->accountMovement($account, null, $from->copy()->subDay(), $filters);

        $rows = $this->lines($from, $to, $filters)
            ->where('jl.account_id', $account->id)
            ->leftJoin('departments as d', 'd.id', '=', 'jl.department_id')
            ->leftJoin('projects as p', 'p.id', '=', 'jl.project_id')
            ->selectRaw('j.id as journal_id, j.reference, j.journal_date, j.memo, j.source_type,
                         jl.description, jl.debit, jl.credit, d.name as department, p.name as project')
            ->orderBy('j.journal_date')
            ->orderBy('j.id')
            ->get();

        $running = $opening;
        $sign = $account->normal_balance->sign();

        $rows = $rows->map(function ($row) use (&$running, $sign) {
            $running = round($running + $sign * ((float) $row->debit - (float) $row->credit), 2);

            return (object) [
                'journal_id' => (int) $row->journal_id,
                'reference' => $row->reference,
                'date' => Carbon::parse($row->journal_date),
                'memo' => $row->memo,
                'source_type' => $row->source_type,
                'description' => $row->description,
                'debit' => round((float) $row->debit, 2),
                'credit' => round((float) $row->credit, 2),
                'department' => $row->department,
                'project' => $row->project,
                'balance' => $running,
            ];
        });

        return ['opening' => $opening, 'rows' => $rows, 'closing' => $running];
    }

    /**
     * Net profit for a window: revenue (class 4000) less every cost class.
     * The single definition used by the distribution engine and by every
     * report that says "net profit".
     *
     * @return array{revenue: float, direct_costs: float, operating_expenses: float, gross_profit: float, net_profit: float}
     */
    public function profitSummary(Carbon $from, Carbon $to, array $filters = []): array
    {
        $revenue = $this->classTotal([4000], $from, $to, $filters);
        $directCosts = $this->classTotal([5000], $from, $to, $filters);
        $operating = $this->classTotal([6000, 7000, 8000, 9000], $from, $to, $filters);

        return [
            'revenue' => $revenue,
            'direct_costs' => $directCosts,
            'operating_expenses' => $operating,
            'gross_profit' => round($revenue - $directCosts, 2),
            'net_profit' => round($revenue - $directCosts - $operating, 2),
        ];
    }

    /** Total cash and bank on hand as at a date. */
    public function cashPosition(Carbon $asAt, array $filters = []): float
    {
        $row = $this->lines(null, $asAt, $filters)
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('a.is_cash_equivalent', true)
            ->selectRaw('SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->first();

        return round((float) ($row->debit ?? 0) - (float) ($row->credit ?? 0), 2);
    }

    /** Retained earnings brought forward: all profit earned before this year. */
    public function retainedEarningsBroughtForward(Carbon $asAt): float
    {
        $yearStart = $this->fiscalYearStart($asAt);

        $row = $this->lines(null, $yearStart->copy()->subDay())
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->whereIn('a.class', [4000, 5000, 6000, 7000, 8000, 9000])
            ->selectRaw('SUM(jl.debit) as debit, SUM(jl.credit) as credit')
            ->first();

        // Credits (revenue) less debits (costs) = profit accumulated to date.
        return round((float) ($row->credit ?? 0) - (float) ($row->debit ?? 0), 2);
    }

    /** First day of the fiscal year containing a date. */
    public function fiscalYearStart(Carbon $date): Carbon
    {
        [$day, $month] = array_map('intval', explode('-', config('allva.fiscal_year_start', '01-01')));

        $start = Carbon::create($date->year, $month, $day)->startOfDay();

        return $start->greaterThan($date) ? $start->subYear() : $start;
    }

    /** Accounts that have ever been posted to — used to trim report noise. */
    public function activeAccountIds(?Carbon $from = null, ?Carbon $to = null): array
    {
        return $this->lines($from, $to)->distinct()->pluck('jl.account_id')->map(fn ($id) => (int) $id)->all();
    }

    public function accountsOfType(AccountType $type): Collection
    {
        return Account::where('type', $type->value)->orderBy('code')->get();
    }
}
