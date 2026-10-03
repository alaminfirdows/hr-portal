<?php

namespace Modules\Employees\Widgets;

use Modules\AccessControl\Services\Access;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Enums\Scope;

final class NewJoinersWidget implements DashboardWidget
{
    public function __construct(private readonly Access $access) {}

    public function key(): string
    {
        return 'employees.new_joiners';
    }

    public function title(): string
    {
        return __('New employees');
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
        return $this->access->scope($user, 'employees.view') === Scope::All;
    }

    public function data(User $user): array
    {
        return [
            'items' => Employee::query()
                ->with('position:id,title')
                ->whereNot('status', 'terminated')
                ->where('joined_on', '>=', now()->subDays(30))
                ->latest('joined_on')
                ->limit(5)
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'title' => $e->full_name,
                    'subtitle' => $e->position?->title,
                    'meta' => $e->joined_on->toDateString(),
                    'href' => route('employees.show', $e),
                ])->all(),
            'empty' => __('No new employees in the last 30 days.'),
        ];
    }
}
