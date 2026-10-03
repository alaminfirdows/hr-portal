<?php

namespace Modules\Projects\Widgets;

use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Enums\Scope;

/**
 * Active projects the user can manage (all of them, or the ones they manage or lead).
 */
final class ActiveProjectsWidget implements DashboardWidget
{
    public function __construct(
        private readonly Access $access,
        private readonly OrgHierarchy $org,
    ) {}

    public function key(): string
    {
        return 'projects.active';
    }

    public function title(): string
    {
        return __('Active projects');
    }

    public function type(): string
    {
        return 'stat';
    }

    public function size(): int
    {
        return 1;
    }

    public function order(): int
    {
        return 20;
    }

    public function visible(User $user): bool
    {
        return $this->access->scope($user, 'projects.manage') !== null;
    }

    public function data(User $user): array
    {
        $scope = $this->access->scope($user, 'projects.manage');
        $me = $this->org->employeeIdForUser($user->id);

        $query = Project::query()->where('projects.status', ProjectStatus::Active->value);

        if ($scope !== Scope::All) {
            $me === null ? $query->whereRaw('1 = 0') : $query->involving($me);
        }

        return [
            'value' => $query->count(),
            'caption' => $scope === Scope::All ? __('Across the company') : __('That you manage'),
            'href' => route('projects.index', ['status' => ProjectStatus::Active->value]),
        ];
    }
}
