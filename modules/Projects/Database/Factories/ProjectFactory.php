<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Enums\ProjectPriority;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Enums\ProjectVisibility;
use Modules\Projects\Models\Project;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' '.fake()->unique()->word(),
            'code' => 'PRJ-'.fake()->unique()->numerify('####'),
            'description' => fake()->sentence(),
            'status' => ProjectStatus::Active,
            'visibility' => ProjectVisibility::Public,
            'priority' => ProjectPriority::Medium,
            'client' => fake()->company(),
            'start_date' => now()->subMonth()->toDateString(),
        ];
    }

    public function visibility(ProjectVisibility $visibility): static
    {
        return $this->state(['visibility' => $visibility]);
    }

    public function status(ProjectStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
