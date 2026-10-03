<?php

namespace Modules\Reports\Reports\Attendance;

use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class LateArrivalsReport extends AbstractReport
{
    public function key(): string
    {
        return 'attendance.late';
    }

    public function title(): string
    {
        return __('Late arrivals');
    }

    public function description(): string
    {
        return __('Every late check-in in the period, latest first.');
    }

    public function group(): string
    {
        return 'Attendance';
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
            ['key' => 'date', 'label' => __('Date'), 'type' => 'text'],
            ['key' => 'code', 'label' => __('Code'), 'type' => 'text'],
            ['key' => 'employee', 'label' => __('Employee'), 'type' => 'text'],
            ['key' => 'department', 'label' => __('Department'), 'type' => 'text'],
            ['key' => 'check_in', 'label' => __('Check-in'), 'type' => 'text'],
            ['key' => 'late_minutes', 'label' => __('Minutes late'), 'type' => 'number'],
        ];
    }

    public function query(array $filters, User $user): array
    {
        $names = $this->departmentNames();

        return $this->employees($user, $filters)
            ->join('attendance_records as a', 'a.employee_id', '=', 'employees.id')
            ->where('a.is_late', true)
            ->whereDate('a.date', '>=', $filters['from'])
            ->whereDate('a.date', '<=', $filters['to'])
            ->orderByDesc('a.date')
            ->orderByDesc('a.late_minutes')
            ->get(['a.date', 'a.check_in_at', 'a.late_minutes', 'employees.employee_code', 'employees.first_name', 'employees.last_name', 'employees.department_id'])
            ->map(fn (object $r) => [
                'date' => substr((string) $r->date, 0, 10),
                'code' => (string) $r->employee_code,
                'employee' => $this->fullName($r->first_name, $r->last_name),
                'department' => $this->departmentLabel($r->department_id === null ? null : (int) $r->department_id, $names),
                'check_in' => $r->check_in_at === null ? '' : substr((string) $r->check_in_at, 11, 5),
                'late_minutes' => (int) $r->late_minutes,
            ])
            ->all();
    }
}
