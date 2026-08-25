<?php

namespace App\Services\Billing;

use App\Models\BusCompany;
use App\Models\RateCard;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Scope of work §4.1 and §4.2.
 *
 * The only sanctioned answer to "what does this bus company pay per student in
 * this month". Rates are effective-dated, so a historical month keeps the rate
 * that applied at the time even after a future increase, and a company-specific
 * rate always beats the general one.
 */
class RateResolver
{
    /** @var array<string,?RateCard> */
    private array $cache = [];

    /**
     * The rate in force for a company on a date.
     *
     * A rate carrying this company's id is a special agreement and wins; if
     * there is none, the general rate (bus_company_id null) applies. Where two
     * rates of the same kind overlap, the one that started most recently wins,
     * so correcting a rate is a matter of adding a new row rather than editing
     * history.
     */
    public function resolve(BusCompany|int $company, Carbon $date): ?RateCard
    {
        $companyId = $company instanceof BusCompany ? $company->id : $company;
        $key = $companyId.'|'.$date->toDateString();

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $specific = RateCard::effectiveOn($date)
            ->where('bus_company_id', $companyId)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        $rate = $specific ?? RateCard::effectiveOn($date)
            ->whereNull('bus_company_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return $this->cache[$key] = $rate;
    }

    /** The same lookup, but refusing to guess when no rate has been set up. */
    public function resolveOrFail(BusCompany $company, Carbon $date): RateCard
    {
        return $this->resolve($company, $date) ?? throw new RuntimeException(
            "No rate is in force for {$company->name} in {$date->format('F Y')}. "
            .'Add a rate card covering that month before billing it.'
        );
    }

    public function amountFor(BusCompany $company, Carbon $date): float
    {
        return (float) $this->resolveOrFail($company, $date)->amount;
    }

    /**
     * Scope of work §8.2 — the rate history report: which rate applied to which
     * period and which company.
     *
     * @return \Illuminate\Support\Collection<int,RateCard>
     */
    public function history(?BusCompany $company = null)
    {
        return RateCard::with(['academicYear', 'busCompany', 'createdBy'])
            ->when($company, fn ($q) => $q->where(fn ($w) => $w
                ->where('bus_company_id', $company->id)
                ->orWhereNull('bus_company_id')))
            ->orderByDesc('effective_from')
            ->get();
    }

    /**
     * Overlap check used before a rate is saved. Two rates of the same scope
     * covering the same day would make billing ambiguous, so the UI warns.
     *
     * @return \Illuminate\Support\Collection<int,RateCard>
     */
    public function overlapping(?int $companyId, Carbon $from, ?Carbon $to, ?int $ignoreId = null)
    {
        return RateCard::query()
            ->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where('bus_company_id', $companyId))
            ->when(! $companyId, fn ($q) => $q->whereNull('bus_company_id'))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereDate('effective_from', '<=', $to?->toDateString() ?? '9999-12-31')
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from->toDateString()))
            ->get();
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
