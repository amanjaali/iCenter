<?php

namespace App\Services;

use App\Enums\OverheadAllocationMethod;
use App\Enums\ProrationMethod;
use App\Enums\RevenueShareBasis;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * The single door to every runtime-configurable value. Anything the scope of
 * work calls "configurable" — the revenue split basis, the proration rule, the
 * overhead allocation method — is read through here and nowhere else.
 */
class SettingsService
{
    private const CACHE_KEY = 'allva.settings';

    /** @var array<string,string>|null */
    private ?array $loaded = null;

    public function all(): array
    {
        return $this->loaded ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::pluck('value', 'key')->all()
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default ?? self::configDefault($key);
        }

        return $value;
    }

    /**
     * The bootstrap default for a key.
     *
     * Setting keys contain dots ("billing.proration_method"), and config() would
     * read those as nested array traversal and find nothing — the defaults array
     * is flat, keyed by the literal dotted string. So it is indexed directly.
     */
    public static function configDefault(string $key): mixed
    {
        return config('allva.settings_defaults')[$key] ?? null;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function float(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return filter_var($this->get($key, $default), FILTER_VALIDATE_BOOL);
    }

    public function set(string $key, mixed $value, ?string $reason = null): void
    {
        $setting = Setting::firstOrNew(['key' => $key]);
        $old = $setting->value;

        $setting->fill([
            'value' => is_array($value) ? json_encode($value) : (string) $value,
            'updated_by' => Auth::id(),
        ]);

        // Group and cast are inferred on first write so a new key can be added
        // from code without a migration.
        if (! $setting->exists) {
            $setting->group = explode('.', $key)[0];
            $setting->cast = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : (is_float($value) ? 'decimal' : 'string'));
        }

        $setting->save();
        $this->flush();

        if ($old !== $setting->value) {
            app(AuditLogger::class)->record(
                'setting_changed',
                $setting,
                oldValues: ['value' => $old],
                newValues: ['value' => $setting->value],
                description: "Changed setting [{$key}]",
                reason: $reason,
            );
        }
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }

    // -- Typed accessors for the decisions that drive money ------------------

    /** §10.1 — gross or net basis for the Cyber Gate share. */
    public function revenueShareBasis(): RevenueShareBasis
    {
        return RevenueShareBasis::tryFrom((string) $this->get('revenue_share.basis'))
            ?? RevenueShareBasis::Gross;
    }

    public function revenueSharePercent(): float
    {
        return $this->float('revenue_share.percent', 50.0);
    }

    public function revenueSharePartnerName(): string
    {
        return (string) $this->get('revenue_share.partner_name', 'Cyber Gate');
    }

    /** §10.2 — the mid-month join and leave rule. */
    public function prorationMethod(): ProrationMethod
    {
        return ProrationMethod::tryFrom((string) $this->get('billing.proration_method'))
            ?? ProrationMethod::Daily;
    }

    public function invoiceDueDays(): int
    {
        return $this->int('billing.invoice_due_days', 15);
    }

    /** §7 — how unattributed overhead is spread across projects. */
    public function overheadAllocationMethod(): OverheadAllocationMethod
    {
        return OverheadAllocationMethod::tryFrom((string) $this->get('allocation.overhead_method'))
            ?? OverheadAllocationMethod::Revenue;
    }

    public function companyName(): string
    {
        return (string) $this->get('company.name', 'ALLVA Company');
    }
}
