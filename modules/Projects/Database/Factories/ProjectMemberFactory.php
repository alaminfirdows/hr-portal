<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Employees\Models\Employee;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;

/**
 * @extends Factory<ProjectMember>
 */
class ProjectMemberFactory extends Factory
{
    protected $model = ProjectMember::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'employee_id' => Employee::factory(),
            'role' => MemberRole::Member,
            'allocation_percent' => 50,
            'joined_on' => now()->subWeek()->toDateString(),
        ];
    }
}
