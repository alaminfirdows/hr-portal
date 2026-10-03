<?php

namespace Modules\Announcements\Actions;

use Illuminate\Database\Eloquent\Builder;
use Modules\Announcements\Enums\Audience;
use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Notifications\AnnouncementPublished;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;

/**
 * Notifies the audience once the announcement is live. Safe to call repeatedly: the first
 * caller claims the announcement atomically, later calls do nothing.
 */
final class DispatchAnnouncement
{
    /**
     * @return int number of people notified
     */
    public function handle(Announcement $announcement): int
    {
        if ($announcement->publish_at->isFuture()) {
            return 0;
        }

        $claimed = Announcement::query()
            ->whereKey($announcement->getKey())
            ->whereNull('dispatched_at')
            ->update(['dispatched_at' => now()]);

        if ($claimed === 0) {
            return 0;
        }

        // Expired before anyone was told: nothing to announce.
        if ($announcement->expires_at !== null && $announcement->expires_at->isPast()) {
            return 0;
        }

        $targets = $announcement->targetIds();

        $userIds = Employee::query()
            ->employed()
            ->whereNotNull('user_id')
            ->when($announcement->audience !== Audience::All, fn (Builder $q) => $q->whereIn(
                match ($announcement->audience) {
                    Audience::Department => 'department_id',
                    Audience::Team => 'team_id',
                    default => 'id',
                },
                $targets,
            ))
            ->pluck('user_id');

        $notification = new AnnouncementPublished($announcement);
        $count = 0;

        User::query()->whereIn('id', $userIds)->where('is_active', true)->each(function (User $user) use ($notification, &$count) {
            $user->notify($notification);
            $count++;
        });

        return $count;
    }
}
