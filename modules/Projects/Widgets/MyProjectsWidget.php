<?php

namespace Modules\Projects\Widgets;

use Modules\AccessControl\Services\Access;
use Modules\Identity\Models\User;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Contracts\OrgHierarchy;

/**
 * Open projects the user manages or belongs to.
 */
final class MyProjectsWidget implements DashboardWidget
{
    public function __construct(
        private readonly Access $access,
        private readonly OrgHierarchy $org,
    ) {}

    public function key(): string
    {
        return 'projects.mine';
    }

    public function title(): string
    {
        return __('My projects');
    }

    public function type(): string
    {
        return 'list';
    }

    public function size(): int
    {
        return 2;
    }

    public function order(): int
    {
        return 60;
    }

    public function visible(User $user): bool
    {
        return $this->access->can($user, 'projects.view') && $this->org->employeeIdForUser($user->id) !== null;
    }

    public function data(User $user): array
    {
        $me = $this->org->employeeIdForUser($user->id);

        $items = $me === null ? [] : Project::query()
            ->involving($me)
            ->whereIn('projects.status', ProjectStatus::openValues())
            ->orderBy('projects.name')
            ->limit(6)
            ->get()
            ->map(fn (Project $p) => [
                'id' => $p->id,
                'title' => $p->name,
                'subtitle' => $p->code,
                'meta' => $p->status->label(),
                'href' => route('projects.show', $p),
            ])->all();

        return [
            'items' => $items,
            'empty' => __('You are not on any project.'),
            'href' => route('projects.index', ['mine' => 1]),
        ];
    }
}
