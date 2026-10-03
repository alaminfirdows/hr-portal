<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Attendance\Database\Factories\ShiftFactory;

/**
 * @property int $id
 * @property string $name
 * @property string $start_time H:i:s
 * @property string $end_time H:i:s
 * @property int $grace_minutes
 * @property int $half_day_minutes Worked time below this counts as a half day
 * @property int $overtime_after_minutes Overtime starts this long after the shift length
 * @property bool $is_default
 * @property bool $is_active
 */
#[Fillable(['name', 'start_time', 'end_time', 'grace_minutes', 'half_day_minutes', 'overtime_after_minutes', 'is_default', 'is_active'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    public const FALLBACK_START = '09:00:00';

    public const FALLBACK_END = '18:00:00';

    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'half_day_minutes' => 'integer',
            'overtime_after_minutes' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Unsaved shift used when no default exists.
     */
    public static function fallback(): self
    {
        return new self([
            'name' => 'Standard',
            'start_time' => self::FALLBACK_START,
            'end_time' => self::FALLBACK_END,
            'grace_minutes' => 15,
            'half_day_minutes' => 240,
            'overtime_after_minutes' => 30,
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Scheduled minutes between start and end (same day).
     */
    public function lengthMinutes(): int
    {
        return max(0, $this->minutesOfDay($this->end_time) - $this->minutesOfDay($this->start_time));
    }

    public function minutesOfDay(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<EmployeeShift, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
    }
}
