<?php

namespace Modules\Documents\Widgets;

use Illuminate\Support\Facades\Gate;
use Modules\Documents\Models\Document;
use Modules\Identity\Models\User;
use Modules\Shared\Contracts\DashboardWidget;

final class ExpiringDocumentsWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'documents.expiring';
    }

    public function title(): string
    {
        return __('Documents expiring soon');
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
        return 71;
    }

    public function visible(User $user): bool
    {
        return Gate::forUser($user)->allows('documents.manage');
    }

    public function data(User $user): array
    {
        return [
            'items' => Document::query()
                ->whereNotNull('expires_on')
                ->whereDate('expires_on', '<=', now()->addDays(30))
                ->orderBy('expires_on')
                ->limit(5)
                ->get()
                ->map(fn (Document $d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'subtitle' => $d->expires_on->isPast() ? __('Expired') : __('Expiring'),
                    'meta' => $d->expires_on->toDateString(),
                    'href' => route('documents.show', $d),
                ])->all(),
            'empty' => __('No documents expiring in the next 30 days.'),
            'href' => route('documents.index'),
        ];
    }
}
