<?php

namespace Modules\Reports\Reports\Attendance;

use Illuminate\Database\Query\JoinClause;
use Modules\Identity\Models\User;
use Modules\Reports\Reports\AbstractReport;

final class AttendanceSummaryReport extends AbstractReport
{
    public function key(): string
    {
        return 'attendance.summary';
    }

    public function title(): string
    {
        return __('Attendance summary');
    }

    public function description(): string
    {
        return __('Present, late, absent days and overtime per employee.');
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
            ['key' => 'code', 'label' => __('Code'), 'type' => 'text'],
            ['key' => 'employee', 'label' => __('Employee'), 'type' => 'text'],
            ['key' => 'department', 'label' => __('Department'), 'type' => 'text'],
            ['key' => 'present', 'label' => __('Present'), 'type' => 'number'],
            ['key' => 'half_day', 'label' => __('Half day'), 'type' => 'number'],
            ['key' => 'late', 'label' => __('Late'), 'type' => 'number'],
            ['key' => 'absent', 'label' => __('Absent'), 'type' => 'number'],
            ['key' => 'on_leave', 'label' => __('On leave'), 'type' => 'number'],
            ['key' => 'overtime_hours', 'label' => __('Overtime (h)'), 'type' => 'decimal'],
        ];
    }

    public function query(array $filters, User $user): array
    {
        $names = $this->departmentNames();

        return $this->employees($user, $filters)
            ->join('attendance_records as a', function (JoinClause $j) use ($filters) {
                $j->on('a.employee_id', '=', 'employees.id')
                    ->whereDate('a.date', '>=', $filters['from'])
                    ->whereDate('a.date', '<=', $filters['to']);
            })
            ->selectRaw(
                "employees.employee_code, employees.first_name, employees.last_name, employees.department_id,
                sum(case when a.status = 'present' then 1 else 0 end) as present,
                sum(case when a.status = 'half_day' then 1 else 0 end) as half_day,
                sum(case when a.is_late = 1 then 1 else 0 end) as late,
                sum(case when a.status = 'absent' then 1 else 0 end) as absent,
                sum(case when a.status = 'on_leave' then 1 else 0 end) as on_leave,
                sum(a.overtime_minutes) as overtime_minutes"
            )
            ->groupBy('employees.id', 'employees.employee_code', 'employees.first_name', 'employees.last_name', 'employees.department_id')
            ->orderBy('employees.first_name')
            ->orderBy('employees.last_name')
            ->get()
            ->map(fn (object $r) => [
                'code' => (string) $r->employee_code,
                'employee' => $this->fullName($r->first_name, $r->last_name),
                'department' => $this->departmentLabel($r->department_id === null ? null : (int) $r->department_id, $names),
                'present' => (int) $r->present,
                'half_day' => (int) $r->half_day,
                'late' => (int) $r->late,
                'absent' => (int) $r->absent,
                'on_leave' => (int) $r->on_leave,
                'overtime_hours' => round((int) $r->overtime_minutes / 60, 2),
            ])
            ->all();
    }
}
