<?php

namespace Modules\Announcements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Announcements\Enums\Audience;
use Modules\Announcements\Enums\Priority;
use Modules\Announcements\Models\Announcement;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'priority' => Priority::Normal,
            'audience' => Audience::All,
            'publish_at' => now()->subMinute(),
        ];
    }
}
