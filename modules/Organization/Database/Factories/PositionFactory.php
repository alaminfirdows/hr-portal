<?php

namespace Modules\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Position;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return ['title' => fake()->unique()->jobTitle(), 'level' => fake()->randomElement(['Junior', 'Mid', 'Senior', 'Lead']), 'department_id' => null];
    }
}
