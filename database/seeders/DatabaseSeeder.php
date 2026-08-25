<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Installation seed: chart of accounts, then everything that depends on it.
 *
 * Demo data is deliberately kept out of the default seed — run
 * `php artisan db:seed --class=DemoDataSeeder` to populate a walkthrough.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ChartOfAccountsSeeder::class,
            CoreSetupSeeder::class,
        ]);
    }
}
