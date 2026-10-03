<?php

namespace Modules\Announcements\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Announcements\Events\AnnouncementChanged;
use Modules\Announcements\Models\Announcement;
use Modules\Identity\Models\User;

final class UpdateAnnouncement
{
    public function __construct(private readonly DispatchAnnouncement $dispatch) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Announcement $announcement, User $actor, array $data): Announcement
    {
        DB::transaction(function () use ($announcement, $actor, $data) {
            $targetIds = ($data['audience'] ?? null) === 'all'
                ? []
                : array_values(array_unique(array_map('intval', (array) ($data['target_ids'] ?? []))));
            unset($data['target_ids']);

            $announcement->fill($data);
            $changes = $announcement->getDirty();
            $before = array_intersect_key($announcement->getOriginal(), $changes);
            $announcement->save();

            $announcement->targets()->delete();
            $announcement->targets()->createMany(array_map(fn (int $id) => ['target_id' => $id], $targetIds));

            AnnouncementChanged::dispatch($announcement, 'updated', $actor->id, $before, $changes);
        });

        $this->dispatch->handle($announcement);

        return $announcement;
    }
}
