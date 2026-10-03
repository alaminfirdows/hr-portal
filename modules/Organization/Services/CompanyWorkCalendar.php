<?php

namespace Modules\Organization\Services;

use Carbon\CarbonInterface;
use Modules\Organization\Models\Holiday;
use Modules\Settings\Services\SettingsRepository;
use Modules\Shared\Contracts\WorkCalendar;

/**
 * Working days from settings, minus non-optional holidays.
 */
final class CompanyWorkCalendar implements WorkCalendar
{
    /** @var array<string, int>|null date => index lookup set */
    private ?array $holidays = null;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $this->isWeekend($date) && ! $this->isHoliday($date);
    }

    public function isWeekend(CarbonInterface $date): bool
    {
        return ! in_array($date->isoWeekday(), $this->settings->organization()->workingDays, true);
    }

    public function isHoliday(CarbonInterface $date): bool
    {
        $this->holidays ??= Holiday::query()
            ->where('is_optional', false)
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->flip()
            ->all();

        return isset($this->holidays[$date->toDateString()]);
    }

    public function workingDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $days = 0;
        $day = $from->toImmutable()->startOfDay();
        $end = $to->toImmutable()->startOfDay();

        while ($day->lte($end)) {
            $days += $this->isWorkingDay($day) ? 1 : 0;
            $day = $day->addDay();
        }

        return $days;
    }

    public function flush(): void
    {
        $this->holidays = null;
    }
}
