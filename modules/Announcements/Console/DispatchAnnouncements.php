<?php

namespace Modules\Announcements\Console;

use Illuminate\Console\Command;
use Modules\Announcements\Actions\DispatchAnnouncement;
use Modules\Announcements\Models\Announcement;

/**
 * Notifies audiences of announcements whose publish time has arrived.
 */
final class DispatchAnnouncements extends Command
{
    protected $signature = 'announcements:dispatch';

    protected $description = 'Notify audiences of announcements that have just been published';

    public function handle(DispatchAnnouncement $dispatch): int
    {
        $sent = 0;

        Announcement::query()
            ->whereNull('dispatched_at')
            ->where('publish_at', '<=', now())
            ->each(function (Announcement $announcement) use ($dispatch, &$sent) {
                $sent += $dispatch->handle($announcement);
            });

        $this->info("Announcement notifications sent: {$sent}.");

        return self::SUCCESS;
    }
}
