<?php

namespace Modules\Attendance\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

/**
 * Raised when HR or a manager edits a record by hand.
 */
final class AttendanceRecordEdited implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly AttendanceRecord $record,
        public readonly array $before,
        public readonly array $after,
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        $r = $this->record;

        return new AuditEntry(
            action: 'attendance.edited',
            description: "Edited attendance of {$r->employee->full_name} for {$r->date->toDateString()}",
            subject: $r,
            before: $this->before,
            after: $this->after,
            actorId: $this->actorId,
        );
    }
}
