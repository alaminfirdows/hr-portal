<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Shared\Support\ModuleRegistry;

/**
 * Runs every module's base seeders (roles, settings). Demo data: `php artisan db:seed --class=DemoSeeder`
 * or set SEED_DEMO=true.
 */
class DatabaseSeeder extends Seeder
{
    public function run(ModuleRegistry $registry): void
    {
        $this->call($registry->seeders());

        if (config('app.seed_demo')) {
            $this->call(DemoSeeder::class);
        }
    }
}
