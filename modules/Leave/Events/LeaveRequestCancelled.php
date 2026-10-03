<?php

namespace Modules\Leave\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Leave\Models\LeaveRequest;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

/**
 * Public module API: Attendance listens to this to drop system leave rows.
 */
final class LeaveRequestCancelled implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly LeaveRequest $request,
        public readonly bool $wasApproved,
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        $r = $this->request;

        return new AuditEntry(
            action: 'leave.cancelled',
            description: "Cancelled leave of {$r->employee->full_name} ({$r->start_date->toDateString()} to {$r->end_date->toDateString()})",
            subject: $r,
            before: ['status' => $this->wasApproved ? 'approved' : 'pending'],
            after: ['status' => 'cancelled'],
            actorId: $this->actorId,
        );
    }
}
