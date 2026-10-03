<?php

namespace Modules\Leave\Services;

use Modules\Employees\Models\Employee;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;

/**
 * Balance reads and the pending/used moves that follow request state changes.
 */
final class LeaveBalanceService
{
    public function __construct(private readonly LeaveBalanceAllocator $allocator) {}

    /**
     * Available days; null means the type is unlimited.
     */
    public function available(Employee|int $employee, LeaveType $type, int $year): ?float
    {
        if ($type->isUnlimited()) {
            return null;
        }

        return $this->balance($employee, $type, $year)->available();
    }

    public function balance(Employee|int $employee, LeaveType $type, int $year): LeaveBalance
    {
        $employee = $employee instanceof Employee ? $employee : Employee::query()->findOrFail($employee);

        return $this->allocator->allocate($employee, $type, $year);
    }

    /**
     * Balances of every active type for one year.
     *
     * @return list<array{type: array{id: int, name: string, code: string, color: string, is_paid: bool}, entitled: float, carried_forward: float, adjustment: float, used: float, pending: float, available: float|null}>
     */
    public function forEmployee(int $employeeId, int $year): array
    {
        $employee = Employee::query()->findOrFail($employeeId);
        $rows = [];

        foreach (LeaveType::query()->active()->orderBy('name')->get() as $type) {
            $balance = $this->balance($employee, $type, $year);

            $rows[] = [
                'type' => [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code,
                    'color' => $type->color,
                    'is_paid' => $type->is_paid,
                ],
                'entitled' => $balance->entitled,
                'carried_forward' => $balance->carried_forward,
                'adjustment' => $balance->adjustment,
                'used' => $balance->used,
                'pending' => $balance->pending,
                'available' => $type->isUnlimited() ? null : $balance->available(),
            ];
        }

        return $rows;
    }

    /**
     * Shift a request's days between pending and used on its balance row.
     */
    public function move(LeaveRequest $request, float $pending, float $used): void
    {
        $balance = $this->balance($request->employee_id, $request->type, $request->start_date->year);

        $locked = LeaveBalance::query()->lockForUpdate()->findOrFail($balance->id);
        $locked->pending = max(0.0, round($locked->pending + $pending, 2));
        $locked->used = max(0.0, round($locked->used + $used, 2));
        $locked->save();
    }
}
