<?php

namespace Modules\Leave\Widgets;

use Modules\Identity\Models\User;
use Modules\Leave\Services\LeaveBalanceService;
use Modules\Shared\Contracts\DashboardWidget;
use Modules\Shared\Contracts\OrgHierarchy;

final class MyLeaveBalanceWidget implements DashboardWidget
{
    public function __construct(
        private readonly OrgHierarchy $org,
        private readonly LeaveBalanceService $balances,
    ) {}

    public function key(): string
    {
        return 'leave.my_balances';
    }

    public function title(): string
    {
        return __('My leave balance');
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
        return 20;
    }

    public function visible(User $user): bool
    {
        return $this->org->employeeIdForUser($user->id) !== null && $user->can('leave.request');
    }

    public function data(User $user): array
    {
        $employeeId = $this->org->employeeIdForUser($user->id);

        $items = $employeeId === null ? [] : collect($this->balances->forEmployee($employeeId, (int) now()->year))
            ->map(fn (array $b) => [
                'id' => $b['type']['id'],
                'title' => $b['type']['name'],
                'subtitle' => null,
                'meta' => $b['available'] === null ? __('Unlimited') : rtrim(rtrim(number_format($b['available'], 2), '0'), '.').' '.__('days'),
                'href' => route('leave.requests.index'),
            ])->all();

        return ['items' => $items, 'empty' => __('No leave types.')];
    }
}
