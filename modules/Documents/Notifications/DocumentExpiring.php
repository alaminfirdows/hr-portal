<?php

namespace Modules\Documents\Notifications;

use Modules\Documents\Models\Document;
use Modules\Shared\Notifications\PortalNotification;

final class DocumentExpiring extends PortalNotification
{
    public function __construct(public readonly Document $document, public readonly int $days) {}

    public function type(): string
    {
        return 'documents.expiring';
    }

    public function title(): string
    {
        return __('Document expiring soon');
    }

    public function body(): string
    {
        return __('":title" expires in :days days.', ['title' => $this->document->title, 'days' => $this->days]);
    }

    public function url(): string
    {
        return route('documents.show', $this->document);
    }

    public function icon(): string
    {
        return 'file-warning';
    }
}
