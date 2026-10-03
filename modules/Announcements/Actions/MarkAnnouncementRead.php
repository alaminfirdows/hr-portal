<?php

namespace Modules\Announcements\Actions;

use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Models\AnnouncementRead;
use Modules\Identity\Models\User;

final class MarkAnnouncementRead
{
    /**
     * Idempotent: the first read time is kept.
     */
    public function handle(Announcement $announcement, User $user): void
    {
        AnnouncementRead::query()->firstOrCreate(
            ['announcement_id' => $announcement->id, 'user_id' => $user->id],
            ['read_at' => now()],
        );
    }
}
