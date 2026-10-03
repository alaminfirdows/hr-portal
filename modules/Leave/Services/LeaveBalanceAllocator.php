<?php

namespace Modules\Leave\Services;

use Carbon\CarbonInterface;
use Modules\Employees\Models\Employee;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveType;

/**
 * Creates and accrues balance rows. The leave year is the calendar year.
 */
final class LeaveBalanceAllocator
{
    /**
     * The balance row for a year, created with its starting entitlement when missing.
     */
    public function allocate(Employee $employee, LeaveType $type, int $year): LeaveBalance
    {
        $existing = LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $created = LeaveBalance::query()->create([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => $year,
            'entitled' => $this->entitlement($employee, $type, $year),
            'carried_forward' => $this->carryForward($employee, $type, $year),
        ]);

        // Load column defaults (adjustment, used, pending) so the model is complete.
        return $created->refresh();
    }

    /**
     * Yearly types get the full quota at year start, pro-rated by join month when enabled.
     * Monthly types start at zero and accrue.
     */
    public function entitlement(Employee $employee, LeaveType $type, int $year): float
    {
        if ($type->accrual_method !== AccrualMethod::Yearly || $type->annual_quota === null) {
            return 0.0;
        }

        $joined = $employee->joined_on;

        if ($joined->year > $year) {
            return 0.0;
        }

        if ($type->prorate_new_joiners && $joined->year === $year) {
            return round($type->annual_quota * (13 - $joined->month) / 12, 2);
        }

        return $type->annual_quota;
    }

    /**
     * min(previous year available, cap).
     */
    public function carryForward(Employee $employee, LeaveType $type, int $year): float
    {
        if ($type->accrual_method === AccrualMethod::None || $type->carry_forward_cap <= 0) {
            return 0.0;
        }

        return $this->carryFrom($type, LeaveBalance::query()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->where('year', $year - 1)
            ->first());
    }

    /**
     * Carry forward from an already loaded previous-year row.
     */
    public function carryFrom(LeaveType $type, ?LeaveBalance $previous): float
    {
        if ($type->accrual_method === AccrualMethod::None || $type->carry_forward_cap <= 0 || $previous === null) {
            return 0.0;
        }

        return round(min(max($previous->available(), 0.0), $type->carry_forward_cap), 2);
    }

    /**
     * An unsaved balance with the starting values allocate() would create. Reads use this so they never write.
     */
    public function preview(Employee $employee, LeaveType $type, int $year, ?LeaveBalance $previous): LeaveBalance
    {
        return new LeaveBalance([
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
            'year' => $year,
            'entitled' => $this->entitlement($employee, $type, $year),
            'carried_forward' => $this->carryFrom($type, $previous),
            'adjustment' => 0.0,
            'used' => 0.0,
            'pending' => 0.0,
        ]);
    }

    /**
     * Adds quota/12 for a month. Idempotent: a month is accrued at most once. Returns whether anything was added.
     */
    public function accrue(Employee $employee, LeaveType $type, CarbonInterface $month): bool
    {
        if ($type->accrual_method !== AccrualMethod::Monthly || $type->annual_quota === null) {
            return false;
        }

        if ($employee->joined_on->greaterThan($month->copy()->endOfMonth())) {
            return false;
        }

        $key = $month->format('Y-m');
        $balance = $this->allocate($employee, $type, (int) $month->year);

        if ($balance->last_accrued_month !== null && $balance->last_accrued_month >= $key) {
            return false;
        }

        $balance->entitled = round($balance->entitled + $type->annual_quota / 12, 2);
        $balance->last_accrued_month = $key;
        $balance->save();

        return true;
    }
}
