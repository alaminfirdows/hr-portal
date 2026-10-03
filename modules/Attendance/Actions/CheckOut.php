<?php

namespace Modules\Attendance\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Services\AttendanceEvaluator;
use Modules\Attendance\Services\ShiftResolver;
use Modules\Shared\Exceptions\DomainException;

/**
 * Web check-out for today's open record. Recomputes status, worked and overtime minutes.
 */
final class CheckOut
{
    public function __construct(
        private readonly ShiftResolver $shifts,
        private readonly AttendanceEvaluator $evaluator,
    ) {}

    public function handle(int $employeeId, ?CarbonImmutable $at = null): AttendanceRecord
    {
        $at ??= now()->toImmutable();

        return DB::transaction(function () use ($employeeId, $at): AttendanceRecord {
            $record = AttendanceRecord::query()
                ->where('employee_id', $employeeId)
                ->whereDate('date', $at->toDateString())
                ->lockForUpdate()
                ->first();

            if ($record === null || $record->check_in_at === null) {
                throw new DomainException(__('Check in before you check out.'), 'attendance');
            }

            if ($record->check_out_at !== null) {
                throw new DomainException(__('You already checked out today.'), 'attendance');
            }

            if ($at->lte($record->check_in_at)) {
                throw new DomainException(__('Check-out must be after check-in.'), 'attendance');
            }

            $record->fill([
                ...$this->evaluator->evaluate($this->shifts->forEmployee($employeeId), $record->check_in_at, $at),
                'check_out_at' => $at,
            ]);
            $record->save();

            return $record;
        });
    }
}
