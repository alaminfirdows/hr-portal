<?php

namespace Modules\Projects\Notifications;

use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Models\Project;
use Modules\Shared\Notifications\PortalNotification;

final class AddedToProject extends PortalNotification
{
    public function __construct(public readonly Project $project, public readonly MemberRole $role) {}

    public function type(): string
    {
        return 'projects.added';
    }

    public function title(): string
    {
        return __('You were added to a project');
    }

    public function body(): string
    {
        return __('You joined :project as :role.', ['project' => $this->project->name, 'role' => strtolower($this->role->label())]);
    }

    public function url(): string
    {
        return route('projects.show', $this->project);
    }

    public function icon(): string
    {
        return 'folder-kanban';
    }
}
