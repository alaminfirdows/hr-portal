<?php

namespace Modules\Projects\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Projects\Models\Project;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class ProjectUpdated implements AuditableEvent
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly Project $project,
        public readonly array $before,
        public readonly array $after,
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        $fields = implode(', ', array_keys($this->after));

        return new AuditEntry(
            action: 'projects.updated',
            description: "Updated project {$this->project->name}: {$fields}",
            subject: $this->project,
            before: $this->before,
            after: $this->after,
            actorId: $this->actorId,
        );
    }
}
