<?php

namespace Modules\Leave\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Leave\Models\LeaveRequest;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class LeaveRequestSubmitted implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly LeaveRequest $request, public readonly ?int $actorId = null) {}

    public function auditEntry(): AuditEntry
    {
        $r = $this->request;

        return new AuditEntry(
            action: 'leave.submitted',
            description: "Submitted {$r->days} day(s) of leave for {$r->employee->full_name}",
            subject: $r,
            after: ['start_date' => $r->start_date->toDateString(), 'end_date' => $r->end_date->toDateString(), 'days' => $r->days],
            actorId: $this->actorId,
        );
    }
}
