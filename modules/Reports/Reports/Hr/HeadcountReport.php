<?php

namespace Modules\Reports\Reports\Hr;

use Illuminate\Database\Query\Builder;
use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class HeadcountReport extends AbstractReport
{
    public function key(): string
    {
        return 'hr.headcount';
    }

    public function title(): string
    {
        return __('Headcount by department');
    }

    public function description(): string
    {
        return __('Employees on the payroll of each department on a given date.');
    }

    public function group(): string
    {
        return 'HR';
    }

    public function filters(): array
    {
        return [['key' => 'to', 'label' => __('As of'), 'type' => 'date']];
    }

    public function columns(): array
    {
        return [
            ['key' => 'department', 'label' => __('Department'), 'type' => 'text'],
            ['key' => 'headcount', 'label' => __('Headcount'), 'type' => 'number'],
        ];
    }

    public function chart(): array
    {
        return ['label' => 'department', 'value' => 'headcount'];
    }

    public function query(array $filters, User $user): array
    {
        $names = $this->departmentNames();

        return $this->employees($user, $filters)
            ->whereDate('employees.joined_on', '<=', $filters['to'])
            ->where(fn (Builder $q) => $q->whereNull('employees.exited_on')->orWhereDate('employees.exited_on', '>', $filters['to']))
            ->selectRaw('employees.department_id, count(*) as total')
            ->groupBy('employees.department_id')
            ->get()
            ->map(fn (object $r) => [
                'department' => $this->departmentLabel($r->department_id === null ? null : (int) $r->department_id, $names),
                'headcount' => (int) $r->total,
            ])
            ->sortBy('department')
            ->values()
            ->all();
    }
}
