<?php

namespace Modules\Announcements\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Announcements\Enums\Audience;
use Modules\Announcements\Enums\Priority;
use Modules\Announcements\Models\Announcement;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;

class AnnouncementsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Announcement::query()->exists()) {
            return;
        }

        $hr = User::query()->where('email', 'hr@example.com')->first() ?? User::query()->first();
        $eng = Department::query()->where('code', 'ENG')->value('id');

        // [title, body, priority, audience, targets, publish offset (days), expires in (days)]
        $rows = [
            ['Welcome to the new HR portal', 'Leave, attendance, documents and announcements now live in one place.', Priority::Important, Audience::All, [], -5, null],
            ['Office closed for the national holiday', 'The head office will be closed. Remote staff: please plan your on-call accordingly.', Priority::Normal, Audience::All, [], -2, 14],
            ['Engineering all-hands on Friday', 'Join us for the quarterly engineering review and roadmap discussion.', Priority::Normal, Audience::Department, $eng ? [$eng] : [], -1, 7],
            ['Security reminder: rotate your passwords', 'Please rotate passwords and enable two-factor authentication this week.', Priority::Urgent, Audience::All, [], -3, 10],
            ['Benefits enrollment opens next week', 'Review the benefits overview document before enrollment starts.', Priority::Normal, Audience::All, [], 3, null],
            ['Annual picnic registration (closed)', 'Registration for last season closed. Thanks to everyone who joined.', Priority::Normal, Audience::All, [], -30, -20],
        ];

        foreach ($rows as [$title, $body, $priority, $audience, $targets, $offset, $expires]) {
            $publishAt = now()->addDays($offset);

            $announcement = Announcement::query()->create([
                'title' => $title,
                'body' => $body,
                'priority' => $priority,
                'audience' => $audience,
                'publish_at' => $publishAt,
                'expires_at' => $expires === null ? null : now()->addDays($expires),
                // Past announcements are history: do not spam notifications when seeding.
                'dispatched_at' => $publishAt->isPast() ? $publishAt : null,
                'created_by' => $hr?->id,
            ]);

            $announcement->targets()->createMany(array_map(fn (int $id) => ['target_id' => $id], $targets));
        }
    }
}
