<?php

namespace Modules\Projects\Actions;

use Modules\Identity\Models\User;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Events\ProjectArchived;
use Modules\Projects\Models\Project;
use Modules\Shared\Exceptions\DomainException;

final class ArchiveProject
{
    public function handle(Project $project, ?User $by = null): Project
    {
        if ($project->status === ProjectStatus::Archived) {
            throw new DomainException(__('This project is already archived.'), 'status');
        }

        $previous = $project->status;
        $project->update(['status' => ProjectStatus::Archived]);

        ProjectArchived::dispatch($project, $previous, $by?->id);

        return $project;
    }
}
