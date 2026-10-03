<?php

namespace Modules\Employees\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Enums\EmploymentType;
use Modules\Employees\Enums\Gender;
use Modules\Employees\Enums\WorkType;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            'employee_code' => 'EMP-'.fake()->unique()->numerify('#####'),
            'first_name' => $first,
            'last_name' => $last,
            'email' => Str::lower($first.'.'.$last.'.'.fake()->unique()->numberBetween(1, 99999)).'@example.com',
            'phone' => fake()->phoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(Gender::cases()),
            'joined_on' => fake()->dateTimeBetween('-6 years', '-1 month')->format('Y-m-d'),
            'status' => EmployeeStatus::Active,
            'employment_type' => EmploymentType::FullTime,
            'work_type' => fake()->randomElement(WorkType::cases()),
            'emergency_contact' => ['name' => fake()->name(), 'relation' => 'Spouse', 'phone' => fake()->phoneNumber()],
            'skills' => fake()->randomElements(['PHP', 'Laravel', 'React', 'SQL', 'Design', 'Accounting', 'Recruiting'], 2),
            'links' => [],
        ];
    }

    /**
     * Linked to a new (or given) user account with the same email.
     */
    public function withUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id ?? User::factory()->state([
                'email' => $attributes['email'],
                'name' => $attributes['first_name'].' '.$attributes['last_name'],
            ]),
        ]);
    }

    public function status(EmployeeStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
