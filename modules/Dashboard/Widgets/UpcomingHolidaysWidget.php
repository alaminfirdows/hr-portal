<?php

namespace Modules\Dashboard\Widgets;

use Modules\Identity\Models\User;
use Modules\Organization\Models\Holiday;
use Modules\Shared\Contracts\DashboardWidget;

final class UpcomingHolidaysWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'organization.upcoming_holidays';
    }

    public function title(): string
    {
        return __('Upcoming holidays');
    }

    public function type(): string
    {
        return 'list';
    }

    public function size(): int
    {
        return 1;
    }

    public function order(): int
    {
        return 80;
    }

    public function visible(User $user): bool
    {
        return true;
    }

    public function data(User $user): array
    {
        return [
            'items' => Holiday::query()
                ->where('date', '>=', today())
                ->orderBy('date')
                ->limit(5)
                ->get()
                ->map(fn (Holiday $h) => [
                    'id' => $h->id,
                    'title' => $h->name,
                    'subtitle' => $h->is_optional ? __('Optional') : null,
                    'meta' => $h->date->format('D, M j'),
                ])->all(),
            'empty' => __('No upcoming holidays.'),
            'href' => route('organization.holidays.index'),
        ];
    }
}
