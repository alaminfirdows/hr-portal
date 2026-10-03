<?php

namespace Modules\Shared\Contracts;

use Carbon\CarbonInterface;

/**
 * Approved leave lookups. Implemented by the Leave module.
 */
interface LeaveCalendar
{
    public function isOnLeave(int $employeeId, CarbonInterface $date): bool;

    public function unpaidLeaveDays(int $employeeId, CarbonInterface $from, CarbonInterface $to): float;

    /**
     * @return list<int>
     */
    public function employeesOnLeave(CarbonInterface $date): array;
}
