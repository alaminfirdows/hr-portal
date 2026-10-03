<?php

namespace Modules\Reports\Reports\Payroll;

use Illuminate\Database\Query\Builder;
use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class SalaryDistributionReport extends AbstractReport
{
    public function key(): string
    {
        return 'payroll.salary_distribution';
    }

    public function title(): string
    {
        return __('Salary distribution');
    }

    public function description(): string
    {
        return __('Current basic salary spread per department (active staff).');
    }

    public function group(): string
    {
        return 'Payroll';
    }

    public function permission(): string
    {
        return 'payroll.manage';
    }

    public function filters(): array
    {
        return [['key' => 'department_id', 'label' => __('Department'), 'type' => 'department']];
    }

    public function columns(): array
    {
        return [
            ['key' => 'department', 'label' => __('Department'), 'type' => 'text'],
            ['key' => 'employees', 'label' => __('Employees'), 'type' => 'number'],
            ['key' => 'min', 'label' => __('Minimum'), 'type' => 'money'],
            ['key' => 'average', 'label' => __('Average'), 'type' => 'money'],
            ['key' => 'max', 'label' => __('Maximum'), 'type' => 'money'],
            ['key' => 'total', 'label' => __('Total'), 'type' => 'money'],
        ];
    }

    public function chart(): array
    {
        return ['label' => 'department', 'value' => 'average'];
    }

    public function query(array $filters, User $user): array
    {
        $names = $this->departmentNames();
        $today = now()->toDateString();

        return $this->employees($user, $filters)
            ->whereNotIn('employees.status', ['terminated', 'invited'])
            ->join('compensations as c', function ($j) use ($today) {
                $j->on('c.employee_id', '=', 'employees.id')
                    ->whereDate('c.effective_from', '<=', $today)
                    ->where(fn (Builder $q) => $q->whereNull('c.effective_to')->orWhereDate('c.effective_to', '>=', $today));
            })
            ->selectRaw('employees.department_id, count(*) as employees, min(c.basic_salary) as min_salary, max(c.basic_salary) as max_salary, sum(c.basic_salary) as total_salary')
            ->groupBy('employees.department_id')
            ->get()
            ->map(fn (object $r) => [
                'department' => $this->departmentLabel($r->department_id === null ? null : (int) $r->department_id, $names),
                'employees' => (int) $r->employees,
                'min' => $this->money($r->min_salary),
                'average' => $this->money((int) round((int) $r->total_salary / max(1, (int) $r->employees))),
                'max' => $this->money($r->max_salary),
                'total' => $this->money($r->total_salary),
            ])
            ->sortBy('department')
            ->values()
            ->all();
    }
}
