<?php

namespace Modules\Attendance\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Services\Access;
use Modules\Attendance\Enums\AttendanceSource;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Events\AttendanceRecordEdited;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Services\AttendanceEvaluator;
use Modules\Attendance\Services\ShiftResolver;
use Modules\Identity\Models\User;
use Modules\Shared\Exceptions\DomainException;

/**
 * Manual create or correction of one day. Needs attendance.manage over the employee. Always audited.
 *
 * With a check-in time, metrics are recomputed from the shift; an explicit status still wins.
 * Without times, a status is required and metrics are zeroed.
 */
final class UpsertAttendanceRecord
{
    private const TRACKED = ['status', 'check_in_at', 'check_out_at', 'is_late', 'late_minutes', 'worked_minutes', 'overtime_minutes', 'note'];

    public function __construct(
        private readonly Access $access,
        private readonly ShiftResolver $shifts,
        private readonly AttendanceEvaluator $evaluator,
    ) {}

    /**
     * @param  array{check_in?: string|null, check_out?: string|null, status?: string|null, note?: string|null}  $data  times are H:i
     */
    public function handle(int $employeeId, string $date, array $data, User $by): AttendanceRecord
    {
        if (! $this->access->can($by, 'attendance.manage', $employeeId)) {
            throw new AuthorizationException(__('You cannot edit this employee\'s attendance.'));
        }

        $day = CarbonImmutable::parse($date)->startOfDay();

        if ($day->isFuture()) {
            throw new DomainException(__('Attendance cannot be edited for a future date.'), 'date');
        }

        $in = $this->time($day, $data['check_in'] ?? null);
        $out = $this->time($day, $data['check_out'] ?? null);

        if ($in === null && $out !== null) {
            throw new DomainException(__('Check-out needs a check-in time.'), 'check_in');
        }

        if ($in !== null && $out !== null && $out->lte($in)) {
            throw new DomainException(__('Check-out must be after check-in.'), 'check_out');
        }

        $status = filled($data['status'] ?? null) ? AttendanceStatus::from((string) $data['status']) : null;

        if ($in === null && $status === null) {
            throw new DomainException(__('Choose a status or enter times.'), 'status');
        }

        return DB::transaction(function () use ($employeeId, $day, $in, $out, $status, $data, $by): AttendanceRecord {
            $record = AttendanceRecord::query()->where('employee_id', $employeeId)->whereDate('date', $day->toDateString())->lockForUpdate()->first()
                ?? new AttendanceRecord(['employee_id' => $employeeId, 'date' => $day->toDateString()]);

            $before = $record->exists ? $this->snapshot($record) : [];

            $metrics = $in !== null
                ? $this->evaluator->evaluate($this->shifts->forEmployee($employeeId), $in, $out)
                : ['status' => $status, 'is_late' => false, 'late_minutes' => 0, 'worked_minutes' => 0, 'overtime_minutes' => 0];

            $record->fill([
                ...$metrics,
                'status' => $status ?? $metrics['status'],
                'check_in_at' => $in,
                'check_out_at' => $out,
                'source' => AttendanceSource::Manual,
                'note' => $data['note'] ?? null,
                'edited_by' => $by->id,
            ]);
            $record->save();
            $record->load('employee');

            AttendanceRecordEdited::dispatch($record, $before, $this->snapshot($record), $by->id);

            return $record;
        });
    }

    private function time(CarbonImmutable $day, ?string $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            throw new DomainException(__('Time must be HH:MM.'), 'check_in');
        }

        [$h, $m] = array_map('intval', explode(':', $value));

        return $day->setTime($h, $m);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(AttendanceRecord $record): array
    {
        $out = [];

        foreach (self::TRACKED as $key) {
            $value = $record->{$key};
            $out[$key] = $value instanceof AttendanceStatus ? $value->value : ($value instanceof CarbonImmutable ? $value->toDateTimeString() : $value);
        }

        return $out;
    }
}
