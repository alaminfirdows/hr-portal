<?php

namespace Modules\Shared\Null;

use Carbon\CarbonInterface;
use Modules\Shared\Contracts\LeaveCalendar;

final class NullLeaveCalendar implements LeaveCalendar
{
    public function isOnLeave(int $employeeId, CarbonInterface $date): bool
    {
        return false;
    }

    public function unpaidLeaveDays(int $employeeId, CarbonInterface $from, CarbonInterface $to): float
    {
        return 0.0;
    }

    public function employeesOnLeave(CarbonInterface $date): array
    {
        return [];
    }
}
