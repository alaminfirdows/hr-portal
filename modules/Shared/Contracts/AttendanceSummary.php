<?php

namespace Modules\Shared\Contracts;

use Carbon\CarbonInterface;

/**
 * Attendance totals for a period. Implemented by the Attendance module.
 */
interface AttendanceSummary
{
    /**
     * @return array{present_days: int, absent_days: int, late_days: int, overtime_minutes: int}
     */
    public function forPeriod(int $employeeId, CarbonInterface $from, CarbonInterface $to): array;
}
