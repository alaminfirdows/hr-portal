<?php

namespace Modules\Employees\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Employees\Models\Employee;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class EmployeeUpdated implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly Employee $employee,
        public readonly array $before,
        public readonly array $after,
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        $fields = implode(', ', array_keys($this->after));

        return new AuditEntry(
            action: 'employees.updated',
            description: "Updated {$this->employee->full_name}: {$fields}",
            subject: $this->employee,
            before: $this->before,
            after: $this->after,
            actorId: $this->actorId,
        );
    }
}
