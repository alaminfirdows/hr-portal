<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Employees\Enums\EmployeeStatus;
use Modules\Employees\Models\Employee;
use Modules\Employees\Models\EmployeeHistory;
use Modules\Identity\Events\InvitationAccepted;
use Modules\Identity\Models\User;
use Modules\Identity\Notifications\InvitationNotification;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;

/**
 * @return array<string, mixed>
 */
function employeePayload(array $overrides = []): array
{
    return [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
        'joined_on' => now()->toDateString(),
        'employment_type' => 'full_time',
        'work_type' => 'onsite',
        ...$overrides,
    ];
}

test('hr creates employee and invites the account', function () {
    Notification::fake();

    $this->actingAs(userWithRole('hr'))
        ->post(route('employees.store'), employeePayload(['invite' => true]))
        ->assertSessionHasNoErrors();

    $employee = Employee::query()->where('email', 'ada@example.com')->firstOrFail();
    expect($employee->status)->toBe(EmployeeStatus::Invited)
        ->and($employee->employee_code)->toStartWith('EMP-')
        ->and($employee->user_id)->not->toBeNull();
    Notification::assertSentTo($employee->user, InvitationNotification::class);
});

test('employee cannot create employees', function () {
    $this->actingAs(userWithRole('employee'))
        ->post(route('employees.store'), employeePayload())
        ->assertForbidden();
});

test('team must belong to selected department', function () {
    $team = Team::factory()->create();
    $other = Department::factory()->create();

    $this->actingAs(userWithRole('hr'))
        ->post(route('employees.store'), employeePayload(['department_id' => $other->id, 'team_id' => $team->id]))
        ->assertSessionHasErrors('team_id');
});

test('manager sees only own team in employee list', function () {
    $manager = userWithRole('manager');
    $report = Employee::factory()->create(['manager_id' => employeeOf($manager)->id]);
    $stranger = Employee::factory()->create();
    forgetHierarchy();

    $this->actingAs($manager)->get(route('employees.index'))
        ->assertInertia(fn ($page) => $page->component('employees/index')
            ->where('employees.data', fn ($rows) => collect($rows)->pluck('id')->contains($report->id)
                && ! collect($rows)->pluck('id')->contains($stranger->id)));

    $this->actingAs($manager)->get(route('employees.show', $stranger))->assertForbidden();
    $this->actingAs($manager)->get(route('employees.show', $report))->assertOk();
});

test('employee cannot open employee records list', function () {
    $this->actingAs(userWithRole('employee'))->get(route('employees.index'))->assertForbidden();
});

test('update records history for tracked fields', function () {
    $employee = Employee::factory()->create();
    $dept = Department::factory()->create();

    $this->actingAs(userWithRole('hr'))
        ->put(route('employees.update', $employee), employeePayload([
            'email' => $employee->email,
            'department_id' => $dept->id,
            'change_reason' => 'Reorg',
        ]))
        ->assertSessionHasNoErrors();

    $history = EmployeeHistory::query()->where('employee_id', $employee->id)->where('field', 'department_id')->first();
    expect($history)->not->toBeNull()
        ->and($history->new_value)->toBe((string) $dept->id)
        ->and($history->reason)->toBe('Reorg');
});

test('manager cycle is blocked', function () {
    $boss = Employee::factory()->create();
    $mid = Employee::factory()->create(['manager_id' => $boss->id]);

    $this->actingAs(userWithRole('hr'))
        ->put(route('employees.update', $boss), employeePayload(['email' => $boss->email, 'manager_id' => $mid->id]))
        ->assertSessionHasErrors('manager_id');
});

test('employee cannot be own manager', function () {
    $e = Employee::factory()->create();

    $this->actingAs(userWithRole('hr'))
        ->put(route('employees.update', $e), employeePayload(['email' => $e->email, 'manager_id' => $e->id]))
        ->assertSessionHasErrors('manager_id');
});

test('updating email syncs the login email', function () {
    $user = User::factory()->create();
    $e = Employee::factory()->withUser($user)->create(['email' => $user->email]);

    $this->actingAs(userWithRole('hr'))
        ->put(route('employees.update', $e), employeePayload(['email' => 'renamed@example.com']))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBe('renamed@example.com');
});

test('terminating employee deactivates user and sets exit date', function () {
    $user = User::factory()->create();
    $e = Employee::factory()->withUser($user)->status(EmployeeStatus::Active)->create(['email' => $user->email]);

    $this->actingAs(userWithRole('hr'))
        ->post(route('employees.status', $e), ['status' => 'terminated', 'reason' => 'Resigned'])
        ->assertSessionHasNoErrors();

    expect($e->fresh()->status)->toBe(EmployeeStatus::Terminated)
        ->and($e->fresh()->exited_on)->not->toBeNull()
        ->and($user->fresh()->is_active)->toBeFalse();
});

test('invalid status transition rejected', function () {
    $e = Employee::factory()->status(EmployeeStatus::Terminated)->create();

    $this->actingAs(userWithRole('hr'))
        ->post(route('employees.status', $e), ['status' => 'active'])
        ->assertSessionHasErrors('status');
});

test('accepting invitation activates invited employee', function () {
    $user = User::factory()->inactive()->create();
    $e = Employee::factory()->withUser($user)->status(EmployeeStatus::Invited)->create(['email' => $user->email]);

    event(new InvitationAccepted($user));

    expect($e->fresh()->status)->toBe(EmployeeStatus::Active);
});

test('employee updates own contact details only', function () {
    $user = userWithRole('employee');
    $before = employeeOf($user);

    $this->actingAs($user)->put(route('employees.profile.update'), [
        'phone' => '+880 1700 000000',
        'skills' => ['PHP', 'React'],
        'first_name' => 'Hacker',
    ])->assertSessionHasNoErrors();

    $after = $before->fresh();
    expect($after->phone)->toBe('+880 1700 000000')
        ->and($after->skills)->toBe(['PHP', 'React'])
        ->and($after->first_name)->toBe($before->first_name);
});

test('directory is visible to every employee and hides private fields', function () {
    Employee::factory()->create(['personal_email' => 'secret@home.test', 'status' => 'active']);

    $this->actingAs(userWithRole('employee'))->get(route('employees.directory'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('employees/directory/index'))
        ->assertDontSee('secret@home.test');
});

test('photo is private and served through auth', function () {
    Storage::fake();
    $user = userWithRole('employee');
    $e = employeeOf($user);

    $this->actingAs(userWithRole('hr'))
        ->post(route('employees.photo.update', $e), ['photo' => UploadedFile::fake()->image('me.jpg', 200, 200)])
        ->assertSessionHasNoErrors();

    expect($e->fresh()->photo_path)->not->toBeNull();
    $this->actingAs($user)->get(route('employees.photo', $e))->assertOk();
    auth()->logout();
    $this->get(route('employees.photo', $e))->assertRedirect(route('login'));
});
