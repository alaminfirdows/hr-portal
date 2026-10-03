<?php

namespace Modules\Employees\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Enums\EmploymentType;
use Modules\Employees\Enums\WorkType;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Location;
use Modules\Organization\Models\Position;
use Modules\Organization\Models\Team;

/**
 * Demo logins (password "password") plus ~40 employees across the demo org.
 */
class EmployeesDemoSeeder extends Seeder
{
    public const LOGINS = [
        'admin@example.com' => ['Alex', 'Admin', 'admin', 'ENG', null, 'Chief Technology Officer'],
        'hr@example.com' => ['Hana', 'Rahman', 'hr', 'HR', 'People Operations', 'HR Manager'],
        'manager@example.com' => ['Maya', 'Chen', 'manager', 'ENG', 'Backend', 'Engineering Manager'],
        'pm@example.com' => ['Paul', 'Morgan', 'project_manager', 'ENG', null, 'Project Manager'],
        'employee@example.com' => ['Eli', 'Turner', 'employee', 'ENG', 'Backend', 'Software Engineer'],
    ];

    public function run(): void
    {
        if (Employee::query()->exists()) {
            return;
        }

        $departments = Department::query()->pluck('id', 'code');
        $teams = Team::query()->get();
        $positions = Position::query()->pluck('id', 'title');
        $office = Location::query()->where('name', 'Head Office')->value('id');
        $code = 1;
        $people = [];

        foreach (self::LOGINS as $email => [$first, $last, $role, $dept, $team, $position]) {
            $user = User::query()->firstOrCreate(['email' => $email], [
                'name' => "{$first} {$last}",
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $user->syncRolesBySlug($role === 'employee' ? ['employee'] : [$role, 'employee']);

            $people[$role] = Employee::factory()->create([
                'user_id' => $user->id,
                'employee_code' => sprintf('EMP-%05d', $code++),
                'first_name' => $first,
                'last_name' => $last,
                'email' => $email,
                'department_id' => $departments[$dept] ?? null,
                'team_id' => $team ? $teams->firstWhere('name', $team)?->id : null,
                'position_id' => $positions[$position] ?? null,
                'location_id' => $office,
                'joined_on' => now()->subYears(3)->toDateString(),
            ]);
        }

        $admin = $people['admin'] ?? throw new LogicException('Demo admin missing.');
        $hr = $people['hr'] ?? throw new LogicException('Demo HR missing.');
        $manager = $people['manager'] ?? throw new LogicException('Demo manager missing.');
        $employee = $people['employee'] ?? throw new LogicException('Demo employee missing.');
        $projectManager = $people['project_manager'] ?? throw new LogicException('Demo project manager missing.');

        $manager->update(['manager_id' => $admin->id]);
        $employee->update(['manager_id' => $manager->id]);
        $projectManager->update(['manager_id' => $admin->id]);
        Department::query()->where('code', 'ENG')->update(['head_employee_id' => $admin->id]);
        Department::query()->where('code', 'HR')->update(['head_employee_id' => $hr->id]);
        Team::query()->where('name', 'Backend')->update(['lead_employee_id' => $manager->id]);

        // Team leads for the remaining teams, then members reporting to them.
        foreach ($teams as $team) {
            $lead = $team->name === 'Backend' ? $manager : Employee::factory()->create([
                'employee_code' => sprintf('EMP-%05d', $code++),
                'department_id' => $team->department_id,
                'team_id' => $team->id,
                'position_id' => $positions->random(),
                'location_id' => $office,
                'manager_id' => $admin->id,
            ]);
            $team->update(['lead_employee_id' => $lead->id]);

            foreach (range(1, $team->name === 'Backend' ? 5 : 4) as $i) {
                Employee::factory()->create([
                    'employee_code' => sprintf('EMP-%05d', $code++),
                    'department_id' => $team->department_id,
                    'team_id' => $team->id,
                    'position_id' => $positions->random(),
                    'location_id' => fake()->boolean(80) ? $office : Location::query()->where('name', 'Remote')->value('id'),
                    'manager_id' => $lead->id,
                    'employment_type' => fake()->randomElement([EmploymentType::FullTime, EmploymentType::FullTime, EmploymentType::Contract, EmploymentType::Intern]),
                    'work_type' => fake()->randomElement(WorkType::cases()),
                    'joined_on' => fake()->dateTimeBetween('-4 years', '-10 days')->format('Y-m-d'),
                ]);
            }
        }

        // A few lifecycle examples.
        $sample = Employee::query()->whereNull('user_id')->inRandomOrder()->limit(3)->get();
        $sample->get(0)?->update(['status' => EmployeeStatus::OnLeave]);
        $sample->get(1)?->update(['status' => EmployeeStatus::Terminated, 'exited_on' => now()->subMonths(2)->toDateString()]);
        $sample->get(2)?->update(['status' => EmployeeStatus::Invited, 'joined_on' => now()->addWeek()->toDateString()]);
    }
}
