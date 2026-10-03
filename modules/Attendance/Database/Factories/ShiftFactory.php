<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\Models\Shift;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'grace_minutes' => 15,
            'half_day_minutes' => 240,
            'overtime_after_minutes' => 30,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
