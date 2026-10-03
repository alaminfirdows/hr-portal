<?php

namespace Modules\Reports\Reports\Hr;

use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class MovementsReport extends AbstractReport
{
    public function key(): string
    {
        return 'hr.movements';
    }

    public function title(): string
    {
        return __('Joiners and leavers');
    }

    public function description(): string
    {
        return __('Employees who joined or left in the period, by department.');
    }

    public function group(): string
    {
        return 'HR';
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
            ['key' => 'joiners', 'label' => __('Joiners'), 'type' => 'number'],
            ['key' => 'leavers', 'label' => __('Leavers'), 'type' => 'number'],
            ['key' => 'net', 'label' => __('Net change'), 'type' => 'number'],
        ];
    }

    public function chart(): array
    {
        return ['label' => 'department', 'value' => 'joiners'];
    }

    public function query(array $filters, User $user): array
    {
        $names = $this->departmentNames();

        return $this->employees($user, $filters)
            ->selectRaw(
                'employees.department_id,
                sum(case when employees.joined_on >= ? and employees.joined_on <= ? then 1 else 0 end) as joiners,
                sum(case when employees.exited_on >= ? and employees.exited_on <= ? then 1 else 0 end) as leavers',
                [$filters['from'], $filters['to'], $filters['from'], $filters['to']],
            )
            ->groupBy('employees.department_id')
            ->get()
            ->filter(fn (object $r) => (int) $r->joiners > 0 || (int) $r->leavers > 0)
            ->map(fn (object $r) => [
                'department' => $this->departmentLabel($r->department_id === null ? null : (int) $r->department_id, $names),
                'joiners' => (int) $r->joiners,
                'leavers' => (int) $r->leavers,
                'net' => (int) $r->joiners - (int) $r->leavers,
            ])
            ->sortBy('department')
            ->values()
            ->all();
    }
}
