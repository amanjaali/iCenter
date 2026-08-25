<?php

namespace Tests;

use App\Models\Account;
use App\Models\Department;
use App\Models\Project;
use App\Services\Accounting\PeriodService;
use App\Services\AuditLogger;
use App\Services\SettingsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CoreSetupSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Install the chart of accounts and the core setup.
     *
     * Almost every test needs a real chart of accounts — the posting engine
     * resolves accounts by code — so this is shared rather than repeated.
     */
    protected function installChartOfAccounts(): void
    {
        app(AuditLogger::class)->withoutLogging(function () {
            $this->seed(ChartOfAccountsSeeder::class);
            $this->seed(CoreSetupSeeder::class);
        });

        app(SettingsService::class)->flush();
        Account::clearResolvedCache();
    }

    protected function department(string $code = 'TEC'): Department
    {
        return Department::where('code', $code)->firstOrFail();
    }

    protected function project(string $code = 'ETRK'): Project
    {
        return Project::where('code', $code)->firstOrFail();
    }

    protected function periods(): PeriodService
    {
        return app(PeriodService::class);
    }
}
