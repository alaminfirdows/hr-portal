<?php

namespace Modules\Leave\Services;

use Carbon\CarbonInterface;
use Modules\Leave\Enums\DayPart;
use Modules\Shared\Contracts\WorkCalendar;

/**
 * Leave days = working days in the range (weekends and holidays excluded). A half day is 0.5 of a single working day.
 */
final class LeaveDayCalculator
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    public function days(CarbonInterface $start, CarbonInterface $end, DayPart $part = DayPart::Full): float
    {
        $working = $this->calendar->workingDaysBetween($start, $end);

        if ($part->isHalf()) {
            return $start->isSameDay($end) && $working === 1 ? 0.5 : 0.0;
        }

        return (float) $working;
    }
}
