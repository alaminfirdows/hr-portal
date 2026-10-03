<?php

namespace Modules\Shared\Contracts;

use Carbon\CarbonInterface;

/**
 * Company working days and holidays. Implemented by the Organization module.
 */
interface WorkCalendar
{
    public function isWorkingDay(CarbonInterface $date): bool;

    public function isWeekend(CarbonInterface $date): bool;

    public function isHoliday(CarbonInterface $date): bool;

    /**
     * Working days between two dates, both inclusive.
     */
    public function workingDaysBetween(CarbonInterface $from, CarbonInterface $to): int;
}
