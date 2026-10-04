<?php

namespace Modules\Attendance;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Attendance\Console\FinalizeAttendanceCommand;
use Modules\Attendance\Database\Seeders\AttendanceDemoSeeder;
use Modules\Attendance\Database\Seeders\ShiftSeeder;
use Modules\Attendance\Listeners\ClearLeaveDays;
use Modules\Attendance\Listeners\MarkLeaveDays;
use Modules\Attendance\Services\EloquentAttendanceSummary;
use Modules\Attendance\Widgets\LateTodayWidget;
use Modules\Attendance\Widgets\MyAttendanceTodayWidget;
use Modules\Attendance\Widgets\TeamAttendanceTodayWidget;
use Modules\Leave\Events\LeaveRequestApproved;
use Modules\Leave\Events\LeaveRequestCancelled;
use Modules\Shared\Contracts\AttendanceSummary;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class AttendanceServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Attendance';
    }

    public function seedOrder(): int
    {
        return 65;
    }

    public function permissions(): array
    {
        return [
            'attendance.check_in' => 'Check in and out',
            'attendance.view' => 'View attendance records',
            'attendance.manage' => 'Edit attendance records',
            'shifts.manage' => 'Manage shifts and shift assignments',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('My attendance', 'attendance.my', 'clock', 'attendance.check_in', 'Attendance', 500, 'attendance.my'),
            new NavItem('Records', 'attendance.records.index', 'calendar-clock', 'attendance.view', 'Attendance', 510, 'attendance.records.*'),
            new NavItem('Shifts', 'attendance.shifts.index', 'timer', 'shifts.manage', 'Attendance', 520, 'attendance.shifts.*'),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [MyAttendanceTodayWidget::class, TeamAttendanceTodayWidget::class, LateTodayWidget::class];
    }

    public function seeders(): array
    {
        return [ShiftSeeder::class];
    }

    public function demoSeeders(): array
    {
        return [AttendanceDemoSeeder::class];
    }

    protected function listeners(): array
    {
        return [
            LeaveRequestApproved::class => [MarkLeaveDays::class],
            LeaveRequestCancelled::class => [ClearLeaveDays::class],
        ];
    }

    protected function consoleCommands(): array
    {
        return [FinalizeAttendanceCommand::class];
    }

    protected function contracts(): array
    {
        return [AttendanceSummary::class => EloquentAttendanceSummary::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('attendance:finalize')->dailyAt('23:55');
    }
}
