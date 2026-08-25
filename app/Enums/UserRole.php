<?php

namespace App\Enums;

/**
 * Scope of work §9.2. Four roles, fixed by the specification. Fine-grained
 * abilities are derived from the role in App\Providers\AuthServiceProvider so
 * that a role's rights are defined in exactly one place.
 */
enum UserRole: string
{
    case Administrator = 'administrator';
    case Accountant = 'accountant';
    case Operations = 'operations';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Accountant => 'Accountant',
            self::Operations => 'Operations / employee',
            self::Partner => 'Partner',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Administrator => 'User management, rate table configuration, system settings.',
            self::Accountant => 'Full rights to create, edit and post entries; run all reports.',
            self::Operations => 'Register bus companies, buses and students only; no access to financial entries.',
            self::Partner => 'Read-only access to all financial data and reports.',
        };
    }

    /** Roles that may see money. Operations explicitly may not (§9.2). */
    public function seesFinancials(): bool
    {
        return $this !== self::Operations;
    }

    /** Only the accountant posts entries; partners are read-only (§9.1). */
    public function postsEntries(): bool
    {
        return $this === self::Accountant;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $r) => [$r->value => $r->label()])
            ->all();
    }
}
