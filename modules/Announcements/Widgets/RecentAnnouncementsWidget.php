<?php

namespace Modules\Announcements\Widgets;

use Illuminate\Support\Facades\Gate;
use Modules\Announcements\Models\Announcement;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;

final class RecentAnnouncementsWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'announcements.recent';
    }

    public function title(): string
    {
        return __('Announcements');
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
        return 10;
    }

    public function visible(User $user): bool
    {
        return Gate::forUser($user)->allows('announcements.view');
    }

    public function data(User $user): array
    {
        return [
            'items' => Announcement::query()
                ->published()
                ->visibleTo($user)
                ->withReadState($user)
                ->orderByDesc('publish_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get()
                ->map(fn (Announcement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'subtitle' => $a->is_read ? $a->priority->label() : $a->priority->label().' · '.__('New'),
                    'meta' => $a->publish_at->toDateString(),
                    'href' => route('announcements.show', $a),
                ])->all(),
            'empty' => __('No announcements right now.'),
            'href' => route('announcements.index'),
        ];
    }
}
