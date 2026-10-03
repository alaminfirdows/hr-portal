<?php

namespace Modules\Reports\Reports\Leave;

use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class LeaveBalancesReport extends AbstractReport
{
    public function key(): string
    {
        return 'leave.balances';
    }

    public function title(): string
    {
        return __('Leave balances');
    }

    public function description(): string
    {
        return __('Entitlement, used, pending and remaining days per employee and leave type for a year.');
    }

    public function group(): string
    {
        return 'Leave';
    }

    public function filters(): array
    {
        return [
            ['key' => 'year', 'label' => __('Year'), 'type' => 'year'],
            ['key' => 'department_id', 'label' => __('Department'), 'type' => 'department'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => __('Code'), 'type' => 'text'],
            ['key' => 'employee', 'label' => __('Employee'), 'type' => 'text'],
            ['key' => 'department', 'label' => __('Department'), 'type' => 'text'],
            ['key' => 'leave_type', 'label' => __('Leave type'), 'type' => 'text'],
            ['key' => 'entitled', 'label' => __('Entitled'), 'type' => 'decimal'],
            ['key' => 'used', 'label' => __('Used'), 'type' => 'decimal'],
            ['key' => 'pending', 'label' => __('Pending'), 'type' => 'decimal'],
            ['key' => 'remaining', 'label' => __('Remaining'), 'type' => 'decimal'],
        ];
    }

    public function query(array $filters, User $user): array
    {
        $names = $this->departmentNames();

        return $this->employees($user, $filters)
            ->join('leave_balances as b', 'b.employee_id', '=', 'employees.id')
            ->join('leave_types as t', 't.id', '=', 'b.leave_type_id')
            ->where('b.year', $filters['year'])
            ->orderBy('employees.first_name')
            ->orderBy('employees.last_name')
            ->orderBy('t.name')
            ->get(['employees.employee_code', 'employees.first_name', 'employees.last_name', 'employees.department_id', 't.name as type_name', 'b.entitled', 'b.carried_forward', 'b.adjustment', 'b.used', 'b.pending'])
            ->map(function (object $r) use ($names) {
                $entitled = (float) $r->entitled + (float) $r->carried_forward + (float) $r->adjustment;

                return [
                    'code' => (string) $r->employee_code,
                    'employee' => $this->fullName($r->first_name, $r->last_name),
                    'department' => $this->departmentLabel($r->department_id === null ? null : (int) $r->department_id, $names),
                    'leave_type' => (string) $r->type_name,
                    'entitled' => round($entitled, 2),
                    'used' => round((float) $r->used, 2),
                    'pending' => round((float) $r->pending, 2),
                    'remaining' => round($entitled - (float) $r->used - (float) $r->pending, 2),
                ];
            })
            ->all();
    }
}
