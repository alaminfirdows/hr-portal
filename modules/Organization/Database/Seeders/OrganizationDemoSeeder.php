<?php

namespace Modules\Organization\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Holiday;
use Modules\Organization\Models\Location;
use Modules\Organization\Models\Position;
use Modules\Organization\Models\Team;

class OrganizationDemoSeeder extends Seeder
{
    public const STRUCTURE = [
        'ENG' => ['Engineering', ['Backend', 'Frontend', 'DevOps']],
        'DES' => ['Design', ['Product Design']],
        'HR' => ['Human Resources', ['People Operations']],
        'FIN' => ['Finance', ['Accounting']],
        'MKT' => ['Marketing', ['Growth']],
    ];

    public const POSITIONS = [
        'Chief Technology Officer', 'Engineering Manager', 'Senior Software Engineer', 'Software Engineer',
        'DevOps Engineer', 'Product Designer', 'HR Manager', 'HR Executive', 'Accountant',
        'Project Manager', 'Marketing Specialist', 'Support Specialist',
    ];

    public function run(): void
    {
        foreach (self::STRUCTURE as $code => [$name, $teams]) {
            $department = Department::query()->firstOrCreate(['code' => $code], ['name' => $name]);

            foreach ($teams as $team) {
                Team::query()->firstOrCreate(['department_id' => $department->id, 'name' => $team]);
            }
        }

        foreach (self::POSITIONS as $position) {
            Position::query()->firstOrCreate(['title' => $position]);
        }

        Location::query()->firstOrCreate(['name' => 'Head Office'], ['address' => '12 Main Road', 'timezone' => config('app.timezone')]);
        Location::query()->firstOrCreate(['name' => 'Remote'], ['timezone' => config('app.timezone')]);

        $year = now()->year;
        foreach ([
            "{$year}-01-01" => ['New Year', false],
            "{$year}-05-01" => ['Labour Day', false],
            "{$year}-12-25" => ['Christmas Day', false],
            "{$year}-12-31" => ['New Year\'s Eve', true],
            ($year + 1).'-01-01' => ['New Year', false],
        ] as $date => [$name, $optional]) {
            Holiday::query()->firstOrCreate(['date' => $date], ['name' => $name, 'is_optional' => $optional]);
        }
    }
}
