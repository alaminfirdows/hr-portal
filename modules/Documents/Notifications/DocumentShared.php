<?php

namespace Modules\Documents\Notifications;

use Modules\Documents\Models\Document;
use Modules\Shared\Notifications\PortalNotification;

final class DocumentShared extends PortalNotification
{
    public function __construct(public readonly Document $document, public readonly string $sharedBy) {}

    public function type(): string
    {
        return 'documents.shared';
    }

    public function title(): string
    {
        return __('A document was shared with you');
    }

    public function body(): string
    {
        return __(':user shared ":title" with you.', ['user' => $this->sharedBy, 'title' => $this->document->title]);
    }

    public function url(): string
    {
        return route('documents.show', $this->document);
    }

    public function icon(): string
    {
        return 'file-text';
    }

    public function defaultChannels(): array
    {
        return ['in_app'];
    }
}
