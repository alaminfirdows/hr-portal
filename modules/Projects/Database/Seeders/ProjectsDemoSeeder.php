<?php

namespace Modules\Projects\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Employees\Models\Employee;
use Modules\Projects\Actions\AddProjectMember;
use Modules\Projects\Actions\CreateProject;
use Modules\Projects\Actions\SaveProjectField;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Enums\ProjectPriority;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Enums\ProjectVisibility;
use Modules\Projects\Models\Project;

/**
 * Eight demo projects with mixed visibility, members and custom field values.
 */
class ProjectsDemoSeeder extends Seeder
{
    public function run(CreateProject $create, AddProjectMember $add, SaveProjectField $saveField): void
    {
        if (Project::query()->exists()) {
            return;
        }

        $employees = Employee::query()->employed()->get();
        $pm = $employees->firstWhere('email', 'pm@example.com');

        if ($employees->isEmpty() || $pm === null) {
            return;
        }

        $this->call(ProjectFieldSeeder::class);

        $named = fn (string $email): ?Employee => $employees->firstWhere('email', $email);
        $others = $employees->reject(fn (Employee $e) => $e->id === $pm->id)->values();

        $rows = [
            ['Website Redesign', ProjectStatus::Active, ProjectVisibility::Public, ProjectPriority::High, 'Acme Corp', 'Client', 'React'],
            ['Mobile App', ProjectStatus::Active, ProjectVisibility::Public, ProjectPriority::Critical, 'Globex', 'Client', 'Flutter'],
            ['Payroll Migration', ProjectStatus::Planned, ProjectVisibility::Restricted, ProjectPriority::High, null, 'Internal', 'Laravel'],
            ['Security Audit', ProjectStatus::Active, ProjectVisibility::Private, ProjectPriority::Critical, null, 'Internal', 'Pentest'],
            ['Data Warehouse', ProjectStatus::OnHold, ProjectVisibility::Restricted, ProjectPriority::Medium, 'Initech', 'Client', 'Snowflake'],
            ['ML Research', ProjectStatus::Active, ProjectVisibility::Private, ProjectPriority::Low, null, 'R&D', 'Python'],
            ['Onboarding Portal', ProjectStatus::Completed, ProjectVisibility::Public, ProjectPriority::Medium, null, 'Internal', 'Inertia'],
            ['Legacy Cleanup', ProjectStatus::Planned, ProjectVisibility::Public, ProjectPriority::Low, null, 'Internal', 'PHP'],
        ];

        foreach ($rows as $i => [$name, $status, $visibility, $priority, $client, $type, $tech]) {
            $manager = $i % 2 === 0 ? $pm : ($named('manager@example.com') ?? $pm);

            $project = $create->handle([
                'name' => $name,
                'description' => "{$name} workstream.",
                'status' => $status->value,
                'visibility' => $visibility->value,
                'priority' => $priority->value,
                'manager_employee_id' => $manager->id,
                'client' => $client,
                'budget' => fake()->numberBetween(20, 400) * 100_000,
                'start_date' => now()->subMonths(fake()->numberBetween(1, 6))->toDateString(),
                'end_date' => now()->addMonths(fake()->numberBetween(1, 8))->toDateString(),
            ], [
                'client_name' => $client,
                'project_type' => $type,
                'technology' => $tech,
            ]);

            $crew = $others->where('id', '!=', $manager->id)->shuffle()->take(3);

            if ($i === 0 && ($employee = $named('employee@example.com')) !== null) {
                $crew = $crew->reject(fn (Employee $e) => $e->id === $employee->id)->prepend($employee);
            }

            foreach ($crew->values() as $n => $member) {
                $add->handle($project, $member, $n === 0 ? MemberRole::Lead : MemberRole::Member, fake()->randomElement([25, 50, 50, 100]));
            }
        }
    }
}
