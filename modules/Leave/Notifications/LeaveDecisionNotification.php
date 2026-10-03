<?php

namespace Modules\Leave\Notifications;

use Modules\Leave\Enums\ApprovalDecision;
use Modules\Leave\Models\LeaveRequest;
use Modules\Shared\Notifications\PortalNotification;

/**
 * Sent to the employee when their request is approved or rejected.
 */
final class LeaveDecisionNotification extends PortalNotification
{
    public function __construct(
        public readonly LeaveRequest $request,
        public readonly ApprovalDecision $decision,
        public readonly ?string $comment = null,
    ) {}

    public function type(): string
    {
        return $this->decision === ApprovalDecision::Approved ? 'leave.request_approved' : 'leave.request_rejected';
    }

    public function title(): string
    {
        return $this->decision === ApprovalDecision::Approved
            ? __('Your leave request was approved')
            : __('Your leave request was rejected');
    }

    public function body(): string
    {
        $r = $this->request;
        $text = __(':type from :from to :to.', [
            'type' => $r->type->name,
            'from' => $r->start_date->toFormattedDateString(),
            'to' => $r->end_date->toFormattedDateString(),
        ]);

        return $this->comment ? "{$text} {$this->comment}" : $text;
    }

    public function url(): string
    {
        return route('leave.requests.show', $this->request);
    }

    public function icon(): string
    {
        return $this->decision === ApprovalDecision::Approved ? 'calendar-check' : 'calendar-off';
    }
}
