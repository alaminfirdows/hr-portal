<?php

namespace Modules\Employees\Widgets;

use Modules\AccessControl\Services\Access;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Enums\Scope;

final class HeadcountWidget implements DashboardWidget
{
    public function __construct(private readonly Access $access) {}

    public function key(): string
    {
        return 'employees.headcount';
    }

    public function title(): string
    {
        return __('Total employees');
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
        return 10;
    }

    public function visible(User $user): bool
    {
        return $this->access->scope($user, 'employees.view') === Scope::All;
    }

    public function data(User $user): array
    {
        $new = Employee::query()->employed()->where('joined_on', '>=', now()->startOfMonth())->count();

        return [
            'value' => Employee::query()->employed()->count(),
            'caption' => trans_choice(':count new this month|:count new this month', $new),
            'href' => route('employees.index'),
        ];
    }
}
