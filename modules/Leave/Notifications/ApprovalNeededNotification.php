<?php

namespace Modules\Leave\Notifications;

use Modules\Leave\Models\LeaveRequest;
use Modules\Shared\Notifications\PortalNotification;

final class ApprovalNeededNotification extends PortalNotification
{
    public function __construct(public readonly LeaveRequest $request) {}

    public function type(): string
    {
        return 'leave.approval_needed';
    }

    public function title(): string
    {
        return __('Leave request awaiting your approval');
    }

    public function body(): string
    {
        $r = $this->request;

        return __(':name requested :days day(s) of :type (:from to :to).', [
            'name' => $r->employee->full_name,
            'days' => rtrim(rtrim(number_format($r->days, 2), '0'), '.'),
            'type' => $r->type->name,
            'from' => $r->start_date->toFormattedDateString(),
            'to' => $r->end_date->toFormattedDateString(),
        ]);
    }

    public function url(): string
    {
        return route('leave.requests.show', $this->request);
    }

    public function icon(): string
    {
        return 'calendar-clock';
    }
}
