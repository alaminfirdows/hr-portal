<?php

namespace Modules\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return ['department_id' => Department::factory(), 'name' => ucfirst(fake()->unique()->word()).' Team', 'lead_employee_id' => null];
    }
}
