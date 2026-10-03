<?php

namespace Modules\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Department;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return ['name' => ucfirst(fake()->unique()->word()).' Dept', 'code' => strtoupper(fake()->unique()->lexify('???')), 'description' => null, 'head_employee_id' => null];
    }
}
