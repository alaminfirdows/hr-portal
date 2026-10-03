<?php

namespace Modules\Organization\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Models\Holiday;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    public function definition(): array
    {
        return ['name' => ucfirst(fake()->word().' '.fake()->word()), 'date' => fake()->unique()->dateTimeBetween('-1 year', '+1 year')->format('Y-m-d'), 'is_optional' => false];
    }
}
