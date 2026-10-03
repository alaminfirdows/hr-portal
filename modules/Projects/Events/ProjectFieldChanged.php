<?php

namespace Modules\Projects\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Projects\Models\ProjectFieldDefinition;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

/**
 * A custom field definition was created, updated or deleted.
 */
final class ProjectFieldChanged implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  'created'|'updated'|'deleted'  $change
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly ProjectFieldDefinition $definition,
        public readonly string $change,
        public readonly array $before = [],
        public readonly array $after = [],
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: "projects.field_{$this->change}",
            description: ucfirst($this->change)." project field {$this->definition->label}",
            subject: $this->change === 'deleted' ? null : $this->definition,
            before: $this->before,
            after: $this->after,
            actorId: $this->actorId,
        );
    }
}
