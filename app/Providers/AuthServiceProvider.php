<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Scope of work §9 — access and permissions.
 *
 *   Accountant     full rights to create, edit and post entries; run all reports
 *   Operations     register bus companies, buses and students only;
 *                  no access to financial entries
 *   Partner        read-only access to all financial data and reports
 *   Administrator  user management, rate table configuration, system settings
 *
 * Abilities are derived from the role in this one place so that "what may a
 * partner do" has a single answer rather than being re-decided per screen.
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Ability => the roles that hold it.
     *
     * @var array<string, array<int, UserRole>>
     */
    private const ABILITIES = [
        // Reading money. Everyone except operations (§9.2).
        'view-financials' => [UserRole::Administrator, UserRole::Accountant, UserRole::Partner],
        'view-reports' => [UserRole::Administrator, UserRole::Accountant, UserRole::Partner],

        // Writing to the ledger. The accountant alone — partners are
        // explicitly read-only (§9.1).
        'post-entries' => [UserRole::Accountant],
        'manage-invoices' => [UserRole::Accountant],
        'manage-payments' => [UserRole::Accountant],
        'manage-expenses' => [UserRole::Accountant],
        'manage-payroll' => [UserRole::Accountant],
        'manage-assets' => [UserRole::Accountant],
        'manage-revenue-share' => [UserRole::Accountant],
        'manage-distributions' => [UserRole::Accountant],
        'close-periods' => [UserRole::Accountant, UserRole::Administrator],

        // The operational registry — the operations role's whole remit.
        'view-registry' => [UserRole::Administrator, UserRole::Accountant, UserRole::Operations, UserRole::Partner],
        'manage-registry' => [UserRole::Administrator, UserRole::Accountant, UserRole::Operations],

        // Configuration. Administrator, with the accountant able to read the
        // chart of accounts they post to.
        'view-rates' => [UserRole::Administrator, UserRole::Accountant, UserRole::Partner],
        'manage-rates' => [UserRole::Administrator],
        'manage-chart-of-accounts' => [UserRole::Administrator, UserRole::Accountant],
        'manage-users' => [UserRole::Administrator],
        'manage-settings' => [UserRole::Administrator],
        'manage-partners' => [UserRole::Administrator],
        'view-audit-trail' => [UserRole::Administrator, UserRole::Accountant, UserRole::Partner],
    ];

    public function boot(): void
    {
        foreach (self::ABILITIES as $ability => $roles) {
            Gate::define($ability, function (User $user) use ($roles) {
                // A deactivated login keeps nothing.
                return $user->is_active && in_array($user->role, $roles, true);
            });
        }

        // An administrator is not implicitly an accountant: the separation
        // between configuring the system and posting to the ledger is the
        // point of having both roles.
    }

    /** @return array<string, array<int,string>> */
    public static function abilityMatrix(): array
    {
        return collect(self::ABILITIES)
            ->map(fn (array $roles) => array_map(fn (UserRole $r) => $r->value, $roles))
            ->all();
    }
}
