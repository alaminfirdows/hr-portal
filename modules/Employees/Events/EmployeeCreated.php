<?php

namespace Modules\Employees\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Employees\Models\Employee;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class EmployeeCreated implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Employee $employee, public readonly ?int $actorId = null) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: 'employees.created',
            description: "Created employee {$this->employee->full_name} ({$this->employee->employee_code})",
            subject: $this->employee,
            after: $this->employee->only(['employee_code', 'email', 'department_id', 'team_id', 'position_id', 'manager_id', 'status']),
            actorId: $this->actorId,
        );
    }
}
