<?php

namespace Modules\Leave\Notifications;

use Modules\Shared\Notifications\PortalNotification;

final class ApprovalReminderNotification extends PortalNotification
{
    public function __construct(public readonly int $count) {}

    public function type(): string
    {
        return 'leave.approval_reminder';
    }

    public function title(): string
    {
        return __('Leave requests are waiting for you');
    }

    public function body(): string
    {
        return trans_choice(':count leave request has been pending for more than 2 days.|:count leave requests have been pending for more than 2 days.', $this->count, ['count' => $this->count]);
    }

    public function url(): string
    {
        return route('leave.approvals.index');
    }

    public function icon(): string
    {
        return 'calendar-clock';
    }
}
