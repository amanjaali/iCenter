<?php

namespace App\Models;

use App\Enums\AccountSubtype;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scope of work §6 — the chart of accounts.
 */
class Account extends Model
{
    use Auditable;

    protected $fillable = [
        'code', 'name', 'name_ar', 'type', 'subtype', 'normal_balance', 'class',
        'parent_id', 'description', 'is_postable', 'is_system', 'is_active',
        'default_nature', 'requires_department', 'requires_project',
        'is_cash_equivalent', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'subtype' => AccountSubtype::class,
            'normal_balance' => NormalBalance::class,
            'class' => 'integer',
            'is_postable' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'requires_department' => 'boolean',
            'requires_project' => 'boolean',
            'is_cash_equivalent' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function displayName(): string
    {
        return $this->code.' — '.$this->name;
    }

    /**
     * Signed movement for a set of debit/credit totals, in the direction that
     * makes the account's own balance positive.
     */
    public function signedBalance(float $debit, float $credit): float
    {
        return $this->normal_balance === NormalBalance::Debit
            ? round($debit - $credit, 2)
            : round($credit - $debit, 2);
    }

    /** @var array<string,self> */
    private static array $codeCache = [];

    /**
     * Look an account up by the code the posting engine refers to it by.
     *
     * Cached per request: a single posting run resolves the same handful of
     * system accounts repeatedly.
     */
    public static function byCode(string $code): self
    {
        return self::$codeCache[$code] ??= static::where('code', $code)->firstOrFail();
    }

    /** Drop the code cache — needed between tests, which rebuild the chart. */
    public static function clearResolvedCache(): void
    {
        self::$codeCache = [];
    }

    /** Resolve one of the config('allva.accounts') aliases to its account. */
    public static function system(string $alias): self
    {
        $code = config("allva.accounts.{$alias}");

        if (! $code) {
            throw new \RuntimeException("No account code configured for the alias [{$alias}].");
        }

        return static::byCode($code);
    }

    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true)->where('is_active', true);
    }

    public function scopeOfType(Builder $query, AccountType ...$types): Builder
    {
        return $query->whereIn('type', array_map(fn (AccountType $t) => $t->value, $types));
    }

    public function scopeInClass(Builder $query, int ...$classes): Builder
    {
        return $query->whereIn('class', $classes);
    }
}
