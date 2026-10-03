<?php

use Modules\Employees\Models\Employee;

/**
 * @return array<string, mixed>
 */
function authzPayload(string $email): array
{
    return [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => $email,
        'joined_on' => now()->toDateString(),
        'employment_type' => 'full_time',
        'work_type' => 'onsite',
    ];
}

test('unauthorized employee create is 403 before uniqueness validation', function () {
    $existing = Employee::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs(userWithRole('employee'))->post(route('employees.store'), authzPayload($existing->email))->assertForbidden();
});

test('unauthorized employee update is 403 before uniqueness validation', function () {
    $other = Employee::factory()->create();
    $taken = Employee::factory()->create();

    $this->actingAs(userWithRole('employee'))->put(route('employees.update', $other), authzPayload($taken->email))->assertForbidden();
});
