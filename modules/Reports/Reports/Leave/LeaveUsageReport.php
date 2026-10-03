<?php

namespace Modules\Reports\Reports\Leave;

use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class LeaveUsageReport extends AbstractReport
{
    public function key(): string
    {
        return 'leave.usage';
    }

    public function title(): string
    {
        return __('Leave usage');
    }

    public function description(): string
    {
        return __('Approved leave days per leave type and department for requests starting in the period.');
    }

    public function group(): string
    {
        return 'Leave';
    }

    public function filters(): array
    {
        return [
            ['key' => 'from', 'label' => __('From'), 'type' => 'date'],
            ['key' => 'to', 'label' => __('To'), 'type' => 'date'],
            ['key' => 'department_id', 'label' => __('Department'), 'type' => 'department'],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'department', 'label' => __('Department'), 'type' => 'text'],
            ['key' => 'leave_type', 'label' => __('Leave type'), 'type' => 'text'],
            ['key' => 'requests', 'label' => __('Requests'), 'type' => 'number'],
            ['key' => 'days', 'label' => __('Days'), 'type' => 'decimal'],
        ];
    }

    public function chart(): array
    {
        return ['label' => 'leave_type', 'value' => 'days'];
    }

    public function query(array $filters, User $user): array
    {
        $names = $this->departmentNames();

        return $this->employees($user, $filters)
            ->join('leave_requests as r', 'r.employee_id', '=', 'employees.id')
            ->join('leave_types as t', 't.id', '=', 'r.leave_type_id')
            ->where('r.status', 'approved')
            ->whereDate('r.start_date', '>=', $filters['from'])
            ->whereDate('r.start_date', '<=', $filters['to'])
            ->selectRaw('employees.department_id, t.name as type_name, count(*) as requests, sum(r.days) as days')
            ->groupBy('employees.department_id', 't.id', 't.name')
            ->get()
            ->map(fn (object $r) => [
                'department' => $this->departmentLabel($r->department_id === null ? null : (int) $r->department_id, $names),
                'leave_type' => (string) $r->type_name,
                'requests' => (int) $r->requests,
                'days' => round((float) $r->days, 2),
            ])
            ->sortBy([['department', 'asc'], ['leave_type', 'asc']])
            ->values()
            ->all();
    }
}
