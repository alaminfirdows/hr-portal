<?php

use Modules\Employees\Models\Employee;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Holiday;

test('hr can create, update and delete a department', function () {
    $hr = userWithRole('hr');

    $this->actingAs($hr)->post(route('organization.departments.store'), ['name' => 'Research', 'code' => 'RND'])
        ->assertSessionHasNoErrors();
    $dept = Department::query()->where('name', 'Research')->firstOrFail();

    $this->actingAs($hr)->put(route('organization.departments.update', $dept), ['name' => 'R&D', 'code' => 'RND'])
        ->assertSessionHasNoErrors();
    expect($dept->fresh()->name)->toBe('R&D');

    $this->actingAs($hr)->delete(route('organization.departments.destroy', $dept))->assertSessionHasNoErrors();
    expect(Department::query()->find($dept->id))->toBeNull();
});

test('department with employees cannot be deleted', function () {
    $dept = Department::factory()->create();
    Employee::factory()->create(['department_id' => $dept->id]);

    $this->actingAs(userWithRole('hr'))->delete(route('organization.departments.destroy', $dept))
        ->assertSessionHasErrors();
    expect(Department::query()->find($dept->id))->not->toBeNull();
});

test('employee cannot manage departments', function () {
    $this->actingAs(userWithRole('employee'))
        ->post(route('organization.departments.store'), ['name' => 'X', 'code' => 'X'])
        ->assertForbidden();
});

test('holidays list is visible to every employee', function () {
    Holiday::factory()->create();

    $this->actingAs(userWithRole('employee'))->get(route('organization.holidays.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('organization/holidays/index')->has('holidays'));
});

test('employee cannot create holidays', function () {
    $this->actingAs(userWithRole('employee'))
        ->post(route('organization.holidays.store'), ['name' => 'X', 'date' => now()->toDateString()])
        ->assertForbidden();
});
