<?php

namespace Modules\Payroll\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Employees\Models\Employee;
use Modules\Payroll\Models\Compensation;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class CompensationChanged implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Employee $employee,
        public readonly Compensation $compensation,
        public readonly ?int $previousBasic,
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: 'payroll.compensation_changed',
            description: "Changed basic salary of {$this->employee->full_name}",
            subject: $this->employee,
            before: ['basic_salary' => $this->previousBasic],
            after: [
                'basic_salary' => $this->compensation->basic_salary,
                'effective_from' => $this->compensation->effective_from->toDateString(),
                'reason' => $this->compensation->reason,
            ],
            actorId: $this->actorId,
        );
    }
}
