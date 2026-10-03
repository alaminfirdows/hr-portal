<?php

namespace Modules\Attendance\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Enums\AttendanceSource;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Services\AttendanceEvaluator;
use Modules\Attendance\Services\ShiftResolver;
use Modules\Shared\Exceptions\DomainException;

/**
 * Web check-in. One per day; a calendar row made by the system (weekend, holiday, leave) is replaced.
 */
final class CheckIn
{
    public function __construct(
        private readonly ShiftResolver $shifts,
        private readonly AttendanceEvaluator $evaluator,
    ) {}

    public function handle(int $employeeId, ?string $ip = null, ?CarbonImmutable $at = null): AttendanceRecord
    {
        $at ??= now()->toImmutable();
        $date = $at->toDateString();

        return DB::transaction(function () use ($employeeId, $ip, $at, $date): AttendanceRecord {
            $record = AttendanceRecord::query()
                ->where('employee_id', $employeeId)
                ->whereDate('date', $date)
                ->lockForUpdate()
                ->first();

            if ($record?->check_in_at !== null) {
                throw new DomainException(__('You already checked in today.'), 'attendance');
            }

            $result = $this->evaluator->evaluate($this->shifts->forEmployee($employeeId), $at, null);

            $record ??= new AttendanceRecord(['employee_id' => $employeeId, 'date' => $date]);
            $record->fill([
                ...$result,
                'check_in_at' => $at,
                'check_out_at' => null,
                'source' => AttendanceSource::Web,
                'ip_address' => $ip,
            ]);
            $record->save();

            return $record;
        });
    }
}
