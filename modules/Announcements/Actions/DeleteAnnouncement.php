<?php

namespace Modules\Announcements\Actions;

use Modules\Announcements\Events\AnnouncementChanged;
use Modules\Announcements\Models\Announcement;
use Modules\Identity\Models\User;

final class DeleteAnnouncement
{
    public function handle(Announcement $announcement, User $actor): void
    {
        $announcement->delete();

        AnnouncementChanged::dispatch($announcement, 'deleted', $actor->id, $announcement->only(['title', 'audience']));
    }
}
