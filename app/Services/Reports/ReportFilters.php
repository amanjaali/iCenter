<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;

/**
 * Scope of work §8 — "All reports must be filterable by date range, project,
 * and department, and must be exportable."
 *
 * One value object carries those filters through every report so the filter bar
 * behaves identically everywhere.
 */
final class ReportFilters
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?int $projectId = null,
        public readonly ?int $departmentId = null,
        public readonly ?int $busCompanyId = null,
        public readonly ?int $partnerId = null,
        public readonly ?string $nature = null,
        public readonly ?string $treatment = null,
    ) {}

    public static function fromRequest(array $input): self
    {
        $to = isset($input['to']) && $input['to']
            ? Carbon::parse($input['to'])->endOfDay()
            : Carbon::today()->endOfMonth();

        $from = isset($input['from']) && $input['from']
            ? Carbon::parse($input['from'])->startOfDay()
            : $to->copy()->startOfYear();

        // A backwards range is a typo, not an instruction: swap rather than
        // silently returning nothing.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return new self(
            from: $from,
            to: $to,
            projectId: self::intOrNull($input['project_id'] ?? null),
            departmentId: self::intOrNull($input['department_id'] ?? null),
            busCompanyId: self::intOrNull($input['bus_company_id'] ?? null),
            partnerId: self::intOrNull($input['partner_id'] ?? null),
            nature: $input['nature'] ?? null ?: null,
            treatment: $input['treatment'] ?? null ?: null,
        );
    }

    /** The shape LedgerService expects. */
    public function ledgerFilters(): array
    {
        return array_filter([
            'project_id' => $this->projectId,
            'department_id' => $this->departmentId,
            'bus_company_id' => $this->busCompanyId,
            'partner_id' => $this->partnerId,
            'nature' => $this->nature,
            'treatment' => $this->treatment,
        ]);
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->from->copy()->startOfMonth())
            && $this->to->isSameDay($this->to->copy()->endOfMonth())) {
            return $this->from->isSameMonth($this->to)
                ? $this->from->format('F Y')
                : $this->from->format('M Y').' – '.$this->to->format('M Y');
        }

        return $this->from->format('j M Y').' – '.$this->to->format('j M Y');
    }

    /** Every month touched by the range, as first-of-month dates. */
    public function months(): array
    {
        $months = [];
        $cursor = $this->from->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($this->to)) {
            $months[] = $cursor->copy();
            $cursor->addMonth();
        }

        return $months;
    }

    public function withRange(Carbon $from, Carbon $to): self
    {
        return new self($from, $to, $this->projectId, $this->departmentId,
            $this->busCompanyId, $this->partnerId, $this->nature, $this->treatment);
    }

    public function toArray(): array
    {
        return [
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'project_id' => $this->projectId,
            'department_id' => $this->departmentId,
            'bus_company_id' => $this->busCompanyId,
            'partner_id' => $this->partnerId,
            'nature' => $this->nature,
            'treatment' => $this->treatment,
        ];
    }

    private static function intOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '' || $value === '0') ? null : (int) $value;
    }
}
