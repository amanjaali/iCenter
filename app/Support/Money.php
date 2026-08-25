<?php

namespace App\Support;

/**
 * Money handling for the ledger.
 *
 * All amounts are stored as decimal(18,2) and manipulated here as float only
 * after rounding, never accumulated unrounded. Iraqi Dinar is presented
 * without minor units, but the ledger keeps two decimal places so that a
 * prorated fraction of a monthly fee is not silently lost — the rounding to
 * whole dinars happens once, at the invoice line, not on every student.
 */
final class Money
{
    public const SCALE = 2;

    public static function round(float|int|string $amount): float
    {
        return round((float) $amount, self::SCALE);
    }

    public static function isZero(float|int|string $amount): bool
    {
        return abs((float) $amount) < 0.005;
    }

    public static function equals(float|int|string $a, float|int|string $b): bool
    {
        return abs((float) $a - (float) $b) < 0.005;
    }

    /** Exact figure to two places — for error messages, never for display. */
    public static function exact(float|int|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0), self::SCALE, '.', ',');
    }

    /** Format for display: whole dinars with thousand separators. */
    public static function format(float|int|string|null $amount, bool $withCode = false): string
    {
        $decimals = (int) config('allva.currency.display_decimals', 0);
        $formatted = number_format((float) ($amount ?? 0), $decimals);

        return $withCode
            ? $formatted.' '.config('allva.currency.code', 'IQD')
            : $formatted;
    }

    /**
     * Round to a whole currency unit. Iraqi Dinar has no minor units in
     * circulation, so a billable amount is always a whole number of dinars —
     * the fractions that proration produces are resolved here, once, rather
     * than being carried into the ledger and reappearing as one-dinar
     * discrepancies between an invoice and its own lines.
     */
    public static function whole(float|int|string $amount): float
    {
        return (float) round((float) $amount, 0);
    }

    /** Accounting presentation: negatives in parentheses, zero as a dash. */
    public static function accounting(float|int|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);

        if (self::isZero($value)) {
            return '—';
        }

        return $value < 0
            ? '('.self::format(abs($value)).')'
            : self::format($value);
    }

    /**
     * Split an amount across weights so the parts add back to exactly the
     * whole. The largest-remainder method: everyone gets their floor, then the
     * leftover minor units go to the largest fractions. Without this, five
     * partners at 20% of an odd amount would not sum to the amount declared.
     *
     * @param  array<array-key, float>  $weights
     * @return array<array-key, float>
     */
    public static function allocate(float $total, array $weights): array
    {
        $weightSum = array_sum($weights);

        if ($weightSum <= 0) {
            return array_map(fn () => 0.0, $weights);
        }

        $unit = 10 ** self::SCALE;
        $totalUnits = (int) round($total * $unit);

        $shares = [];
        $remainders = [];
        $assigned = 0;

        foreach ($weights as $key => $weight) {
            $exact = $totalUnits * ($weight / $weightSum);
            $floor = (int) floor($exact);
            $shares[$key] = $floor;
            $remainders[$key] = $exact - $floor;
            $assigned += $floor;
        }

        $leftover = $totalUnits - $assigned;

        if ($leftover !== 0) {
            // Distribute the remaining units (or claw back, if total was
            // negative) starting with the largest fractional part.
            arsort($remainders);
            $keys = array_keys($remainders);
            $step = $leftover > 0 ? 1 : -1;

            for ($i = 0; $i < abs($leftover); $i++) {
                $shares[$keys[$i % count($keys)]] += $step;
            }
        }

        return array_map(fn (int $units) => $units / $unit, $shares);
    }
}
