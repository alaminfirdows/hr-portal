<?php

namespace Modules\Projects\Policies;

use Illuminate\Auth\Access\Response;
use Modules\Identity\Models\User;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectAccess;

/**
 * Abilities here are model-level (view/update/archive/manageMembers). Permission keys
 * such as projects.view are checked separately by the controllers.
 */
final class ProjectPolicy
{
    public function __construct(private readonly ProjectAccess $projects) {}

    public function view(User $user, Project $project): Response
    {
        if ($this->projects->canOpen($user, $project)) {
            return Response::allow();
        }

        return $this->projects->canSee($user, $project)
            ? Response::deny(__('This project is restricted to its members.'))
            : Response::denyAsNotFound();
    }

    public function update(User $user, Project $project): bool
    {
        return $this->projects->canManage($user, $project);
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->projects->canManage($user, $project);
    }

    public function manageMembers(User $user, Project $project, ?int $employeeId = null): bool
    {
        return $this->projects->canManageMembers($user, $project, $employeeId);
    }
}
