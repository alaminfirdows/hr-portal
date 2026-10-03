<?php

namespace Modules\Attendance\Http\Resources;

use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\Shift;

/**
 * Array shapes sent to pages.
 */
final class AttendancePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function record(AttendanceRecord $r): array
    {
        return [
            'id' => $r->id,
            'employee_id' => $r->employee_id,
            'date' => $r->date->toDateString(),
            'weekday' => $r->date->format('D'),
            'check_in' => $r->check_in_at?->format('H:i'),
            'check_out' => $r->check_out_at?->format('H:i'),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'status_color' => $r->status->color(),
            'is_late' => $r->is_late,
            'late_minutes' => $r->late_minutes,
            'worked_minutes' => $r->worked_minutes,
            'overtime_minutes' => $r->overtime_minutes,
            'source' => $r->source->value,
            'source_label' => $r->source->label(),
            'note' => $r->note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function shift(Shift $s): array
    {
        return [
            'id' => $s->exists ? $s->id : null,
            'name' => $s->name,
            'start_time' => substr($s->start_time, 0, 5),
            'end_time' => substr($s->end_time, 0, 5),
            'grace_minutes' => $s->grace_minutes,
            'half_day_minutes' => $s->half_day_minutes,
            'overtime_after_minutes' => $s->overtime_after_minutes,
            'is_default' => $s->is_default,
            'is_active' => $s->is_active,
        ];
    }
}
