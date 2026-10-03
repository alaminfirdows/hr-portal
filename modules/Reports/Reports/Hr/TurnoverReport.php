<?php

namespace Modules\Reports\Reports\Hr;

use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class TurnoverReport extends AbstractReport
{
    public function key(): string
    {
        return 'hr.turnover';
    }

    public function title(): string
    {
        return __('Turnover rate');
    }

    public function description(): string
    {
        return __('Leavers divided by average headcount (start and end of period), by department.');
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
            ['key' => 'start', 'label' => __('Headcount at start'), 'type' => 'number'],
            ['key' => 'end', 'label' => __('Headcount at end'), 'type' => 'number'],
            ['key' => 'leavers', 'label' => __('Leavers'), 'type' => 'number'],
            ['key' => 'rate', 'label' => __('Turnover %'), 'type' => 'percent'],
        ];
    }

    public function chart(): array
    {
        return ['label' => 'department', 'value' => 'rate'];
    }

    public function query(array $filters, User $user): array
    {
        $names = $this->departmentNames();
        $from = $filters['from'];
        $to = $filters['to'];

        // Present on a date: joined on/before it and not yet exited.
        return $this->employees($user, $filters)
            ->selectRaw(
                'employees.department_id,
                sum(case when employees.joined_on <= ? and (employees.exited_on is null or employees.exited_on > ?) then 1 else 0 end) as start_count,
                sum(case when employees.joined_on <= ? and (employees.exited_on is null or employees.exited_on > ?) then 1 else 0 end) as end_count,
                sum(case when employees.exited_on >= ? and employees.exited_on <= ? then 1 else 0 end) as leavers',
                [$from, $from, $to, $to, $from, $to],
            )
            ->groupBy('employees.department_id')
            ->get()
            ->filter(fn (object $r) => (int) $r->start_count > 0 || (int) $r->end_count > 0 || (int) $r->leavers > 0)
            ->map(function (object $r) use ($names) {
                $average = ((int) $r->start_count + (int) $r->end_count) / 2;

                return [
                    'department' => $this->departmentLabel($r->department_id === null ? null : (int) $r->department_id, $names),
                    'start' => (int) $r->start_count,
                    'end' => (int) $r->end_count,
                    'leavers' => (int) $r->leavers,
                    'rate' => $average > 0 ? round((int) $r->leavers / $average * 100, 1) : 0.0,
                ];
            })
            ->sortBy('department')
            ->values()
            ->all();
    }
}
