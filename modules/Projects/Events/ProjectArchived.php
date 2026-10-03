<?php

namespace Modules\Projects\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class ProjectArchived implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Project $project,
        public readonly ProjectStatus $previous,
        public readonly ?int $actorId = null,
    ) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: 'projects.archived',
            description: "Archived project {$this->project->name}",
            subject: $this->project,
            before: ['status' => $this->previous],
            after: ['status' => ProjectStatus::Archived],
            actorId: $this->actorId,
        );
    }
}
