<?php

namespace Modules\Attendance\Enums;

use Modules\Shared\Concerns\HasOptions;

enum AttendanceStatus: string
{
    use HasOptions;

    case Present = 'present';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
    case Weekend = 'weekend';

    public function color(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Absent => 'red',
            self::HalfDay => 'amber',
            self::OnLeave => 'blue',
            self::Holiday => 'purple',
            self::Weekend => 'gray',
        };
    }

    /**
     * Statuses that come from a real check-in rather than the calendar.
     */
    public function isWorked(): bool
    {
        return $this === self::Present || $this === self::HalfDay;
    }
}
