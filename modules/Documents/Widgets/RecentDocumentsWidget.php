<?php

namespace Modules\Documents\Widgets;

use Illuminate\Support\Facades\Gate;
use Modules\Documents\Models\Document;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;

final class RecentDocumentsWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'documents.recent';
    }

    public function title(): string
    {
        return __('Recent documents');
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
        return 70;
    }

    public function visible(User $user): bool
    {
        return Gate::forUser($user)->allows('documents.view');
    }

    public function data(User $user): array
    {
        return [
            'items' => Document::query()
                ->visibleTo($user)
                ->latest()
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'subtitle' => $d->category ?? $d->visibility->label(),
                    'meta' => $d->created_at->toDateString(),
                    'href' => route('documents.show', $d),
                ])->all(),
            'empty' => __('No documents yet.'),
            'href' => route('documents.index'),
        ];
    }
}
