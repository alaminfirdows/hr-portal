<?php

namespace Modules\Shared\Null;

use Carbon\CarbonInterface;
use Modules\Shared\Contracts\WorkCalendar;

/**
 * Monday–Friday, no holidays.
 */
final class WeekdayWorkCalendar implements WorkCalendar
{
    public function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $this->isWeekend($date);
    }

    public function isWeekend(CarbonInterface $date): bool
    {
        return $date->isoWeekday() >= 6;
    }

    public function isHoliday(CarbonInterface $date): bool
    {
        return false;
    }

    public function workingDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $days = 0;

        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day = $day->addDay()) {
            $days += $this->isWorkingDay($day) ? 1 : 0;
        }

        return $days;
    }
}
