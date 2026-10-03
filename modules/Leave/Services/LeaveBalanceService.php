<?php

namespace Modules\Leave\Services;

use Illuminate\Database\Eloquent\Collection;
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
     * Balances of every active type for one year. Read-only: missing rows are previewed, not created.
     *
     * @return list<array{type: array{id: int, name: string, code: string, color: string, is_paid: bool}, entitled: float, carried_forward: float, adjustment: float, used: float, pending: float, available: float|null}>
     */
    public function forEmployee(int $employeeId, int $year): array
    {
        $employees = Employee::query()->whereKey($employeeId)->get();

        abort_if($employees->isEmpty(), 404);

        return $this->forEmployees($employees, $year)[$employeeId] ?? [];
    }

    /**
     * Balances of every active type for many employees using a fixed number of queries. Read-only.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array<int, list<array{type: array{id: int, name: string, code: string, color: string, is_paid: bool}, entitled: float, carried_forward: float, adjustment: float, used: float, pending: float, available: float|null}>>
     */
    public function forEmployees(Collection $employees, int $year): array
    {
        $ids = $employees->pluck('id')->all();
        $types = LeaveType::query()->active()->orderBy('name')->get();

        /** @var Collection<string, LeaveBalance> $rows */
        $rows = LeaveBalance::query()
            ->whereIn('employee_id', $ids)
            ->whereIn('year', [$year, $year - 1])
            ->get()
            ->keyBy(fn (LeaveBalance $b) => "{$b->employee_id}:{$b->leave_type_id}:{$b->year}");

        $result = [];

        foreach ($employees as $employee) {
            $list = [];

            foreach ($types as $type) {
                $balance = $rows->get("{$employee->id}:{$type->id}:{$year}")
                    ?? $this->allocator->preview($employee, $type, $year, $rows->get("{$employee->id}:{$type->id}:".($year - 1)));

                $list[] = [
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

            $result[$employee->id] = $list;
        }

        return $result;
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
