<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Shared\Support\ModuleRegistry;

/**
 * Demo organization, people and module data. Logins: admin@, hr@, manager@, pm@, employee@example.com / password.
 */
class DemoSeeder extends Seeder
{
    public function run(ModuleRegistry $registry): void
    {
        $this->call($registry->seeders(demo: true));
    }
}
