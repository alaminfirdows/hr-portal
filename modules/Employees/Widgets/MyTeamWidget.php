<?php

namespace Modules\Employees\Widgets;

use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Contracts\OrgHierarchy;

/**
 * Direct reports and team members, for anyone who leads people.
 */
final class MyTeamWidget implements DashboardWidget
{
    public function __construct(private readonly OrgHierarchy $org) {}

    public function key(): string
    {
        return 'employees.my_team';
    }

    public function title(): string
    {
        return __('My team');
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
        return 40;
    }

    public function visible(User $user): bool
    {
        return count($this->teamIds($user)) > 0;
    }

    public function data(User $user): array
    {
        return [
            'items' => Employee::query()
                ->with('position:id,title')
                ->whereIn('id', $this->teamIds($user))
                ->employed()
                ->orderBy('first_name')
                ->limit(8)
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'title' => $e->full_name,
                    'subtitle' => $e->position?->title,
                    'meta' => $e->status->label(),
                    'href' => route('employees.directory.show', $e),
                ])->all(),
            'empty' => __('No team members.'),
        ];
    }

    /**
     * @return list<int>
     */
    private function teamIds(User $user): array
    {
        $me = $this->org->employeeIdForUser($user->id);

        return $me === null ? [] : array_values(array_diff($this->org->teamEmployeeIds($me), [$me]));
    }
}
