<?php

namespace Modules\Employees\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Models\Employee;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class EmployeeStatusChanged implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Employee $employee,
        public readonly EmployeeStatus $from,
        public readonly EmployeeStatus $to,
        public readonly ?string $reason = null,
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: 'employees.status_changed',
            description: "Changed {$this->employee->full_name} status from {$this->from->label()} to {$this->to->label()}",
            subject: $this->employee,
            before: ['status' => $this->from->value],
            after: ['status' => $this->to->value, 'reason' => $this->reason],
            actorId: $this->actorId,
        );
    }
}
