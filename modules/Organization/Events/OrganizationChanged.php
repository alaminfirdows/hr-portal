<?php

namespace Modules\Organization\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

/**
 * Any create/update/delete of an org unit (department, team, position, location, holiday).
 */
final class OrganizationChanged implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  'created'|'updated'|'deleted'  $change
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly Model $subject,
        public readonly string $change,
        public readonly string $label,
        public readonly array $before = [],
        public readonly array $after = [],
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        $type = strtolower(class_basename($this->subject));

        return new AuditEntry(
            action: "organization.{$type}.{$this->change}",
            description: ucfirst($this->change)." {$type} {$this->label}",
            subject: $this->change === 'deleted' ? null : $this->subject,
            before: $this->before,
            after: $this->after,
            actorId: $this->actorId,
        );
    }
}
