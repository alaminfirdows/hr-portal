<?php

namespace Modules\Attendance\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\AttendanceRecordFactory;
use Modules\Attendance\Enums\AttendanceSource;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;

/**
 * @property int $id
 * @property int $employee_id
 * @property CarbonImmutable $date
 * @property CarbonImmutable|null $check_in_at
 * @property CarbonImmutable|null $check_out_at
 * @property AttendanceStatus $status
 * @property bool $is_late
 * @property int $late_minutes
 * @property int $worked_minutes
 * @property int $overtime_minutes
 * @property AttendanceSource $source
 * @property string|null $ip_address
 * @property string|null $note
 * @property int|null $edited_by
 * @property-read Employee $employee
 * @property-read User|null $editor
 */
#[Fillable([
    'employee_id', 'date', 'check_in_at', 'check_out_at', 'status', 'is_late', 'late_minutes', 'worked_minutes',
    'overtime_minutes', 'source', 'ip_address', 'note', 'edited_by',
])]
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'check_in_at' => 'immutable_datetime',
            'check_out_at' => 'immutable_datetime',
            'status' => AttendanceStatus::class,
            'is_late' => 'boolean',
            'late_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'source' => AttendanceSource::class,
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @param  list<int>|null  $ids  null = no restriction
     */
    public function scopeWithinIds(Builder $query, ?array $ids): void
    {
        if ($ids !== null) {
            $query->whereIn('attendance_records.employee_id', $ids);
        }
    }

    /**
     * Inclusive date range. Dates may be stored with a midnight time, so compare by date.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBetweenDates(Builder $query, string $from, string $to): void
    {
        $query->whereDate('attendance_records.date', '>=', $from)->whereDate('attendance_records.date', '<=', $to);
    }

    /**
     * A calendar-derived row (not a real check-in) created by the system.
     */
    public function isSystemRow(): bool
    {
        return $this->source === AttendanceSource::System && $this->check_in_at === null;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
