<?php

namespace Modules\Shared\Null;

use Carbon\CarbonInterface;
use Modules\Shared\Contracts\AttendanceSummary;

final class NullAttendanceSummary implements AttendanceSummary
{
    public function forPeriod(int $employeeId, CarbonInterface $from, CarbonInterface $to): array
    {
        return ['present_days' => 0, 'absent_days' => 0, 'late_days' => 0, 'overtime_minutes' => 0];
    }
}
