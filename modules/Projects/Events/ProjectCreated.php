<?php

namespace Modules\Projects\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Projects\Models\Project;
use Modules\Shared\Contracts\AuditableEvent;
use Modules\Shared\Data\AuditEntry;

final class ProjectCreated implements AuditableEvent
{
    use Dispatchable;

    public function __construct(public readonly Project $project, public readonly ?int $actorId = null) {}

    public function auditEntry(): AuditEntry
    {
        return new AuditEntry(
            action: 'projects.created',
            description: "Created project {$this->project->name} ({$this->project->code})",
            subject: $this->project,
            after: [
                'status' => $this->project->status,
                'visibility' => $this->project->visibility,
                'manager_employee_id' => $this->project->manager_employee_id,
            ],
            actorId: $this->actorId,
        );
    }
}
