<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Attendance\Models\Shift;

/**
 * Default 09:00-18:00 shift. Skipped when any default shift already exists.
 */
class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        if (Shift::query()->where('is_default', true)->exists()) {
            return;
        }

        Shift::query()->create([
            'name' => 'Standard day',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'grace_minutes' => 15,
            'half_day_minutes' => 240,
            'overtime_after_minutes' => 30,
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}
