<?php

namespace Modules\Reports\Reports\Projects;

use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class WorkloadReport extends AbstractReport
{
    public function key(): string
    {
        return 'projects.workload';
    }

    public function title(): string
    {
        return __('Employee workload');
    }

    public function description(): string
    {
        return __('Total allocation across active projects per employee. Over 100% means over-allocated.');
    }

    public function group(): string
    {
        return 'Projects';
    }

    public function filters(): array
    {
        return [['key' => 'department_id', 'label' => __('Department'), 'type' => 'department']];
    }

    public function columns(): array
    {
        return [
            ['key' => 'code', 'label' => __('Code'), 'type' => 'text'],
            ['key' => 'employee', 'label' => __('Employee'), 'type' => 'text'],
            ['key' => 'department', 'label' => __('Department'), 'type' => 'text'],
            ['key' => 'projects', 'label' => __('Active projects'), 'type' => 'number'],
            ['key' => 'allocation', 'label' => __('Allocation %'), 'type' => 'percent'],
        ];
    }

    public function query(array $filters, User $user): array
    {
        $names = $this->departmentNames();

        return $this->employees($user, $filters)
            ->join('project_members as pm', 'pm.employee_id', '=', 'employees.id')
            ->join('projects as p', fn ($j) => $j->on('p.id', '=', 'pm.project_id')->where('p.status', 'active'))
            ->tap(fn ($q) => $this->openableProjects($q, $user, 'p'))
            ->selectRaw('employees.employee_code, employees.first_name, employees.last_name, employees.department_id, count(*) as project_count, sum(pm.allocation_percent) as allocation')
            ->groupBy('employees.id', 'employees.employee_code', 'employees.first_name', 'employees.last_name', 'employees.department_id')
            ->orderByDesc('allocation')
            ->orderBy('employees.first_name')
            ->get()
            ->map(fn (object $r) => [
                'code' => (string) $r->employee_code,
                'employee' => $this->fullName($r->first_name, $r->last_name),
                'department' => $this->departmentLabel($r->department_id === null ? null : (int) $r->department_id, $names),
                'projects' => (int) $r->project_count,
                'allocation' => (int) $r->allocation,
            ])
            ->all();
    }
}
