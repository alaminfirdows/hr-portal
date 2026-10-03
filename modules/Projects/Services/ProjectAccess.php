<?php

namespace Modules\Projects\Services;

use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Enums\ProjectVisibility;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Enums\Scope;

/**
 * Project-level authorization. Permission scopes keep their global meaning
 * (all / own) but "own" is resolved against projects the user manages or leads.
 */
final class ProjectAccess
{
    public function __construct(private readonly Access $access) {}

    private function employeeId(User $user): ?int
    {
        return app(OrgHierarchy::class)->employeeIdForUser($user->id);
    }

    /**
     * Manager of the project or a member holding the lead role.
     */
    public function leads(User $user, Project $project): bool
    {
        $me = $this->employeeId($user);

        if ($me === null) {
            return false;
        }

        return $project->manager_employee_id === $me
            || ProjectMember::query()
                ->where('project_id', $project->id)
                ->where('employee_id', $me)
                ->where('role', MemberRole::Lead->value)
                ->exists();
    }

    /**
     * Manager or any kind of member.
     */
    public function involved(User $user, Project $project): bool
    {
        $me = $this->employeeId($user);

        if ($me === null) {
            return false;
        }

        return $project->manager_employee_id === $me
            || ProjectMember::query()->where('project_id', $project->id)->where('employee_id', $me)->exists();
    }

    /**
     * Can open the project page (details). Restricted projects are listed for everyone
     * but opened only by members; private ones are invisible to outsiders.
     */
    public function canOpen(User $user, Project $project): bool
    {
        if (! $this->access->can($user, 'projects.view')) {
            return false;
        }

        if ($this->access->scope($user, 'projects.manage') === Scope::All) {
            return true;
        }

        return $project->visibility === ProjectVisibility::Public || $this->involved($user, $project);
    }

    /**
     * Batch version of canOpen for lists (one query instead of one per project).
     *
     * @return callable(Project): bool
     */
    public function opener(User $user): callable
    {
        if (! $this->access->can($user, 'projects.view')) {
            return fn (Project $project): bool => false;
        }

        if ($this->access->scope($user, 'projects.manage') === Scope::All) {
            return fn (Project $project): bool => true;
        }

        $me = $this->employeeId($user);
        $mine = $me === null ? [] : Project::query()->involving($me)->pluck('projects.id')->map(fn ($id) => (int) $id)->all();

        return fn (Project $project): bool => $project->visibility === ProjectVisibility::Public || in_array($project->id, $mine, true);
    }

    /**
     * Whether the project should be shown at all (list, or 404 on direct access).
     */
    public function canSee(User $user, Project $project): bool
    {
        if ($project->visibility !== ProjectVisibility::Private) {
            return true;
        }

        return $this->canOpen($user, $project);
    }

    public function canManage(User $user, Project $project): bool
    {
        return match ($this->access->scope($user, 'projects.manage')) {
            null => false,
            Scope::All => true,
            default => $this->leads($user, $project),
        };
    }

    /**
     * Add/edit/remove members. Scope own: projects I manage or lead. Team/department:
     * any project I can open, restricted to employees inside my scope.
     */
    public function canManageMembers(User $user, Project $project, ?int $employeeId = null): bool
    {
        $scope = $this->access->scope($user, 'projects.members.manage');

        return match ($scope) {
            null => false,
            Scope::All => true,
            Scope::Own => $this->leads($user, $project),
            default => $this->canOpen($user, $project) && $this->withinScope($user, $employeeId),
        };
    }

    private function withinScope(User $user, ?int $employeeId): bool
    {
        if ($employeeId === null) {
            return true;
        }

        $ids = $this->access->visibleEmployeeIds($user, 'projects.members.manage');

        return $ids === null || in_array($employeeId, $ids, true);
    }
}
