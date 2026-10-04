<?php

namespace Modules\Leave;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Leave\Console\AccrueLeaveCommand;
use Modules\Leave\Console\AllocateLeaveCommand;
use Modules\Leave\Console\RemindApproversCommand;
use Modules\Leave\Database\Seeders\LeaveDemoSeeder;
use Modules\Leave\Database\Seeders\LeaveTypeSeeder;
use Modules\Leave\Services\EloquentLeaveCalendar;
use Modules\Leave\Widgets\MyLeaveBalanceWidget;
use Modules\Leave\Widgets\OnLeaveTodayWidget;
use Modules\Leave\Widgets\PendingApprovalsWidget;
use Modules\Shared\Contracts\LeaveCalendar;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class LeaveServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Leave';
    }

    public function seedOrder(): int
    {
        return 62;
    }

    public function permissions(): array
    {
        return [
            'leave.request' => 'Request leave for yourself',
            'leave.view' => 'View leave requests, balances and the leave calendar',
            'leave.approve' => 'Approve or reject leave requests',
            'leave.manage' => 'Manage leave types, balances and any request',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('My leave', 'leave.requests.index', 'plane', 'leave.request', 'Time off', 400, 'leave.requests.*'),
            new NavItem('Approvals', 'leave.approvals.index', 'calendar-check', 'leave.approve', 'Time off', 410, 'leave.approvals.*'),
            new NavItem('Leave calendar', 'leave.calendar', 'calendar-days', 'leave.view', 'Time off', 420, 'leave.calendar'),
            new NavItem('Balances', 'leave.balances.index', 'timer', 'leave.manage', 'Time off', 430, 'leave.balances.*'),
            new NavItem('Leave types', 'leave.types.index', 'calendar-off', 'leave.manage', 'Time off', 440, 'leave.types.*'),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [MyLeaveBalanceWidget::class, PendingApprovalsWidget::class, OnLeaveTodayWidget::class];
    }

    public function seeders(): array
    {
        return [LeaveTypeSeeder::class];
    }

    public function demoSeeders(): array
    {
        return [LeaveDemoSeeder::class];
    }

    public function notificationTypes(): array
    {
        return [
            'leave.approval_needed' => 'A leave request needs your approval',
            'leave.request_approved' => 'Your leave request was approved',
            'leave.request_rejected' => 'Your leave request was rejected',
            'leave.approval_reminder' => 'Leave requests are pending your approval for too long',
        ];
    }

    protected function consoleCommands(): array
    {
        return [AllocateLeaveCommand::class, AccrueLeaveCommand::class, RemindApproversCommand::class];
    }

    protected function contracts(): array
    {
        return [LeaveCalendar::class => EloquentLeaveCalendar::class];
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('leave:allocate')->yearlyOn(1, 1, '00:10');
        $schedule->command('leave:accrue')->monthlyOn(1, '00:20');
        $schedule->command('leave:remind-approvers')->dailyAt('08:00');
    }
}
