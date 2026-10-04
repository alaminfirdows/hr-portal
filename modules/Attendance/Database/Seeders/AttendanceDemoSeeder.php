<?php

namespace Modules\Attendance\Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Modules\Attendance\Actions\FinalizeAttendanceDay;
use Modules\Attendance\Enums\AttendanceSource;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\Shift;
use Modules\Attendance\Services\AttendanceEvaluator;
use Modules\Employees\Models\Employee;
use Modules\Shared\Contracts\LeaveCalendar;
use Modules\Shared\Contracts\WorkCalendar;

/**
 * 30 days of history. Present rows come first, then the same
 * finalize pass that runs nightly fills weekends, holidays, approved leave and absences.
 */
class AttendanceDemoSeeder extends Seeder
{
    public function run(
        FinalizeAttendanceDay $finalize,
        AttendanceEvaluator $evaluator,
        WorkCalendar $calendar,
        LeaveCalendar $leave,
    ): void {
        if (AttendanceRecord::query()->exists()) {
            return;
        }

        $this->call(ShiftSeeder::class);
        $shift = Shift::query()->where('is_default', true)->firstOrFail();

        $employees = Employee::query()->employed()->orderBy('id')->get(['id', 'joined_on']);
        $today = CarbonImmutable::today();

        for ($offset = 30; $offset >= 1; $offset--) {
            $day = $today->subDays($offset);

            if ($calendar->isWeekend($day) || $calendar->isHoliday($day)) {
                $finalize->handle($day);

                continue;
            }

            $onLeave = array_flip($leave->employeesOnLeave($day));

            foreach ($employees as $employee) {
                if ($employee->joined_on->gt($day) || isset($onLeave[$employee->id]) || fake()->boolean(6)) {
                    continue;
                }

                $in = $day->setTime(8, 45)->addMinutes(fake()->numberBetween(0, 55));
                $out = $day->setTime(17, 30)->addMinutes(fake()->numberBetween(0, 140));

                if (fake()->boolean(4)) {
                    $out = $in->addHours(3)->addMinutes(fake()->numberBetween(0, 45));
                }

                AttendanceRecord::query()->create([
                    'employee_id' => $employee->id,
                    'date' => $day->toDateString(),
                    'check_in_at' => $in,
                    'check_out_at' => $out,
                    ...$evaluator->evaluate($shift, $in, $out),
                    'source' => AttendanceSource::Web,
                    'ip_address' => '127.0.0.1',
                ]);
            }

            $finalize->handle($day);
        }
    }
}
