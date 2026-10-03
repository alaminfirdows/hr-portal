<?php

namespace Modules\Announcements\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Announcements\Events\AnnouncementChanged;
use Modules\Announcements\Models\Announcement;
use Modules\Identity\Models\User;

final class CreateAnnouncement
{
    public function __construct(private readonly DispatchAnnouncement $dispatch) {}

    /**
     * @param  array<string, mixed>  $data  title, body, priority, audience, target_ids, publish_at?, expires_at?
     */
    public function handle(User $actor, array $data): Announcement
    {
        $announcement = DB::transaction(function () use ($actor, $data): Announcement {
            $targetIds = $this->targetIds($data);
            unset($data['target_ids']);

            $announcement = Announcement::query()->create([
                ...$data,
                'publish_at' => $data['publish_at'] ?? now(),
                'created_by' => $actor->id,
            ]);

            $announcement->targets()->createMany(array_map(fn (int $id) => ['target_id' => $id], $targetIds));

            AnnouncementChanged::dispatch($announcement, 'created', $actor->id, [], $announcement->only(['title', 'audience', 'priority', 'publish_at']));

            return $announcement;
        });

        // Due now: notify right away. Scheduled ones are picked up by announcements:dispatch.
        $this->dispatch->handle($announcement);

        return $announcement;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function targetIds(array $data): array
    {
        if (($data['audience'] ?? null) === 'all') {
            return [];
        }

        return array_values(array_unique(array_map('intval', (array) ($data['target_ids'] ?? []))));
    }
}
