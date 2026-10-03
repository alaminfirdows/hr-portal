<?php

namespace Modules\Announcements\Notifications;

use Illuminate\Support\Str;
use Modules\Announcements\Enums\Priority;
use Modules\Announcements\Models\Announcement;
use Modules\Shared\Notifications\PortalNotification;

final class AnnouncementPublished extends PortalNotification
{
    public function __construct(public readonly Announcement $announcement) {}

    public function type(): string
    {
        return 'announcements.published';
    }

    public function title(): string
    {
        return $this->announcement->title;
    }

    public function body(): string
    {
        return Str::limit(strip_tags($this->announcement->body), 140);
    }

    public function url(): string
    {
        return route('announcements.show', $this->announcement);
    }

    public function icon(): string
    {
        return 'megaphone';
    }

    public function defaultChannels(): array
    {
        return $this->announcement->priority === Priority::Normal ? ['in_app'] : ['in_app', 'email'];
    }
}
