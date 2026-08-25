<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\CoreSetupSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;

/**
 * One command to take a fresh database to a usable system: schema, the chart of
 * accounts from §6, the fiscal calendar, the five partners and one login per
 * role.
 */
class InstallCommand extends Command
{
    protected $signature = 'allva:install
                            {--fresh : Drop every table first — this destroys existing data}
                            {--demo : Also load a worked example of four months of trading}';

    protected $description = 'Install the ALLVA accounting system: schema, chart of accounts and core setup';

    public function handle(): int
    {
        $this->components->info('Installing ALLVA Accounting');

        if ($this->option('fresh')) {
            if (app()->isProduction() && ! confirm('This will DROP every table. Continue?', false)) {
                $this->components->warn('Cancelled.');

                return self::FAILURE;
            }

            $this->components->task('Dropping and recreating the schema', function () {
                $this->callSilent('migrate:fresh', ['--force' => true]);
            });
        } else {
            $this->components->task('Running migrations', function () {
                $this->callSilent('migrate', ['--force' => true]);
            });
        }

        $this->components->task('Installing the chart of accounts (§6)', function () {
            $this->callSilent('db:seed', ['--class' => ChartOfAccountsSeeder::class, '--force' => true]);
        });

        $this->components->task('Core setup: settings, partners, fiscal calendar, rates', function () {
            $this->callSilent('db:seed', ['--class' => CoreSetupSeeder::class, '--force' => true]);
        });

        if ($this->option('demo')) {
            $this->components->task('Loading the demonstration data', function () {
                $this->callSilent('db:seed', ['--class' => \Database\Seeders\DemoDataSeeder::class, '--force' => true]);
            });
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=green>Accounts installed</>', (string) Account::count());
        $this->components->twoColumnDetail('<fg=green>Partners</>', Partner::count().' at '
            .rtrim(rtrim(number_format((float) Partner::active()->sum('ownership_percent'), 2), '0'), '.').'% total');
        $this->components->twoColumnDetail('<fg=green>Periods</>', (string) DB::table('periods')->count());
        $this->components->twoColumnDetail('<fg=green>Users</>', (string) User::count());

        $this->newLine();
        $this->components->info('Sign in with any of these (password: "password"):');

        foreach (User::orderBy('role')->orderBy('name')->get() as $user) {
            $this->components->twoColumnDetail($user->email, $user->role->label());
        }

        $this->newLine();
        $this->components->warn('Change every password before this system is used for real accounting.');

        return self::SUCCESS;
    }
}
