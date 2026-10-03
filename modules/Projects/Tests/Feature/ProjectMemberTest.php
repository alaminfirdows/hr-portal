<?php

use Illuminate\Support\Facades\Notification;
use Modules\Identity\Models\User;
use Modules\Projects\Actions\SyncProjectManager;
use Modules\Projects\Enums\MemberRole;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Enums\ProjectVisibility;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Projects\Notifications\AddedToProject;
use Modules\Projects\Widgets\ActiveProjectsWidget;
use Modules\Projects\Widgets\MyProjectsWidget;
use Modules\Shared\Contracts\ProjectMembership;

/**
 * @return array{0: User, 1: Project}
 */
function pmWithProject(): array
{
    $pm = userWithRole('project_manager');
    $project = Project::factory()->create(['manager_employee_id' => employeeOf($pm)->id]);
    app(SyncProjectManager::class)->handle($project);

    return [$pm, $project];
}

test('project manager adds a member and the member is notified', function () {
    Notification::fake();
    [$pm, $project] = pmWithProject();
    $target = userWithRole('employee');

    $this->actingAs($pm)->post(route('projects.members.store', $project), [
        'employee_id' => employeeOf($target)->id,
        'role' => 'member',
        'allocation_percent' => 40,
    ])->assertSessionHasNoErrors();

    expect(ProjectMember::query()->where('project_id', $project->id)->where('employee_id', employeeOf($target)->id)->value('allocation_percent'))->toBe(40);
    Notification::assertSentTo($target, AddedToProject::class);
});

test('duplicate member is rejected', function () {
    [$pm, $project] = pmWithProject();
    $target = userWithRole('employee');
    $payload = ['employee_id' => employeeOf($target)->id, 'role' => 'member', 'allocation_percent' => 10];

    $this->actingAs($pm)->post(route('projects.members.store', $project), $payload)->assertSessionHasNoErrors();
    $this->actingAs($pm)->post(route('projects.members.store', $project), $payload)->assertSessionHasErrors('employee_id');

    expect(ProjectMember::query()->where('project_id', $project->id)->where('employee_id', employeeOf($target)->id)->count())->toBe(1);
});

test('employee cannot add or remove members', function () {
    $project = Project::factory()->create();
    $employee = userWithRole('employee');
    $target = userWithRole('employee');
    $member = ProjectMember::factory()->create(['project_id' => $project->id]);

    $this->actingAs($employee)->post(route('projects.members.store', $project), [
        'employee_id' => employeeOf($target)->id, 'role' => 'member', 'allocation_percent' => 10,
    ])->assertForbidden();
    $this->actingAs($employee)->delete(route('projects.members.destroy', [$project, $member]))->assertForbidden();
    expect(ProjectMember::query()->find($member->id))->not->toBeNull();
});

test('project manager cannot manage members of someone else project', function () {
    $pm = userWithRole('project_manager');
    $project = Project::factory()->create();
    $target = userWithRole('employee');

    $this->actingAs($pm)->post(route('projects.members.store', $project), [
        'employee_id' => employeeOf($target)->id, 'role' => 'member', 'allocation_percent' => 10,
    ])->assertForbidden();
});

test('project manager removes and updates a member', function () {
    [$pm, $project] = pmWithProject();
    $member = ProjectMember::factory()->create(['project_id' => $project->id, 'allocation_percent' => 20]);

    $this->actingAs($pm)->put(route('projects.members.update', [$project, $member]), ['role' => 'lead', 'allocation_percent' => 60])
        ->assertSessionHasNoErrors();
    expect($member->fresh()->role)->toBe(MemberRole::Lead)->and($member->fresh()->allocation_percent)->toBe(60);

    $this->actingAs($pm)->delete(route('projects.members.destroy', [$project, $member]))->assertSessionHasNoErrors();
    expect(ProjectMember::query()->find($member->id))->toBeNull();
});

test('project manager cannot be removed from members', function () {
    [$pm, $project] = pmWithProject();
    $managerRow = ProjectMember::query()->where('project_id', $project->id)->where('employee_id', employeeOf($pm)->id)->firstOrFail();

    $this->actingAs($pm)->delete(route('projects.members.destroy', [$project, $managerRow]))->assertSessionHasErrors('employee_id');
    $this->actingAs(userWithRole('hr'))->delete(route('projects.members.destroy', [$project, $managerRow]))->assertSessionHasErrors('employee_id');

    expect(ProjectMember::query()->find($managerRow->id))->not->toBeNull();
});

test('manager cannot lose the lead role', function () {
    [$pm, $project] = pmWithProject();
    $managerRow = ProjectMember::query()->where('project_id', $project->id)->where('employee_id', employeeOf($pm)->id)->firstOrFail();

    $this->actingAs($pm)->put(route('projects.members.update', [$project, $managerRow]), ['role' => 'viewer', 'allocation_percent' => 0])
        ->assertSessionHasErrors();
    expect($managerRow->fresh()->role)->toBe(MemberRole::Lead);
});

test('over-allocation warns but does not block', function () {
    $hr = userWithRole('hr');
    $target = userWithRole('employee');
    $employee = employeeOf($target);
    $a = Project::factory()->create();
    $b = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $a->id, 'employee_id' => $employee->id, 'allocation_percent' => 70]);

    $this->actingAs($hr)->post(route('projects.members.store', $b), [
        'employee_id' => $employee->id, 'role' => 'member', 'allocation_percent' => 60,
    ])->assertSessionHasNoErrors()
        ->assertSessionHas('inertia.flash_data.toast.type', 'warning');

    expect(ProjectMember::query()->where('project_id', $b->id)->where('employee_id', $employee->id)->exists())->toBeTrue();
});

test('allocation within the limit gives a success toast', function () {
    $hr = userWithRole('hr');
    $employee = employeeOf(userWithRole('employee'));
    $project = Project::factory()->create();

    $this->actingAs($hr)->post(route('projects.members.store', $project), [
        'employee_id' => $employee->id, 'role' => 'member', 'allocation_percent' => 50,
    ])->assertSessionHas('inertia.flash_data.toast.type', 'success');
});

test('archived projects do not count towards allocation', function () {
    $hr = userWithRole('hr');
    $employee = employeeOf(userWithRole('employee'));
    $old = Project::factory()->status(ProjectStatus::Archived)->create();
    $new = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $old->id, 'employee_id' => $employee->id, 'allocation_percent' => 100]);

    $this->actingAs($hr)->post(route('projects.members.store', $new), [
        'employee_id' => $employee->id, 'role' => 'member', 'allocation_percent' => 80,
    ])->assertSessionHas('inertia.flash_data.toast.type', 'success');
});

test('allocation must be between 0 and 100', function () {
    $project = Project::factory()->create();
    $employee = employeeOf(userWithRole('employee'));

    $this->actingAs(userWithRole('hr'))->post(route('projects.members.store', $project), [
        'employee_id' => $employee->id, 'role' => 'member', 'allocation_percent' => 150,
    ])->assertSessionHasErrors('allocation_percent');
});

test('member of another project is 404', function () {
    $project = Project::factory()->create();
    $foreign = ProjectMember::factory()->create();

    $this->actingAs(userWithRole('hr'))->delete(route('projects.members.destroy', [$project, $foreign]))->assertNotFound();
});

test('changing the manager keeps them as a lead member', function () {
    $hr = userWithRole('hr');
    [, $project] = pmWithProject();
    $newManager = employeeOf(userWithRole('employee'));

    $this->actingAs($hr)->put(route('projects.update', $project), [
        'name' => $project->name, 'visibility' => 'public', 'priority' => 'medium', 'manager_employee_id' => $newManager->id,
    ])->assertSessionHasNoErrors();

    expect(ProjectMember::query()->where('project_id', $project->id)->where('employee_id', $newManager->id)->value('role'))->toBe(MemberRole::Lead);
});

test('membership contract treats manager and members as members', function () {
    $contract = app(ProjectMembership::class);
    $manager = employeeOf(userWithRole('employee'));
    $member = employeeOf(userWithRole('employee'));
    $outsider = employeeOf(userWithRole('employee'));
    $project = Project::factory()->create(['manager_employee_id' => $manager->id]);
    ProjectMember::factory()->create(['project_id' => $project->id, 'employee_id' => $member->id]);

    expect($contract->isMember($project->id, $manager->id))->toBeTrue()
        ->and($contract->isMember($project->id, $member->id))->toBeTrue()
        ->and($contract->isMember($project->id, $outsider->id))->toBeFalse()
        ->and($contract->projectIdsFor($member->id))->toBe([$project->id])
        ->and($contract->projectIdsFor($outsider->id))->toBe([]);
});

test('my projects widget lists my open projects', function () {
    $viewer = userWithRole('employee');
    $project = Project::factory()->create();
    ProjectMember::factory()->create(['project_id' => $project->id, 'employee_id' => employeeOf($viewer)->id]);
    Project::factory()->create();

    $widget = app(MyProjectsWidget::class);

    expect($widget->visible($viewer))->toBeTrue()
        ->and($widget->data($viewer)['items'])->toHaveCount(1);
});

test('active projects widget is hidden for plain employees', function () {
    expect(app(ActiveProjectsWidget::class)->visible(userWithRole('employee')))->toBeFalse()
        ->and(app(ActiveProjectsWidget::class)->visible(userWithRole('hr')))->toBeTrue();
});

test('membership contract lists project options visible to the user', function () {
    $user = userWithRole('employee');
    $public = Project::factory()->create(['name' => 'Alpha']);
    $private = Project::factory()->visibility(ProjectVisibility::Private)->create(['name' => 'Hidden']);
    $mine = Project::factory()->visibility(ProjectVisibility::Private)->create(['name' => 'Mine', 'manager_employee_id' => employeeOf($user)->id]);

    $options = app(ProjectMembership::class)->optionsFor($user);

    expect($options)->toBe([['value' => $public->id, 'label' => 'Alpha'], ['value' => $mine->id, 'label' => 'Mine']])
        ->and(collect($options)->pluck('value'))->not->toContain($private->id);
});

test('members cannot be added by someone who cannot open the project', function () {
    $outsider = userWithRole('manager');
    $project = Project::factory()->visibility(ProjectVisibility::Private)->create();
    $target = userWithRole('employee');

    $this->actingAs($outsider)->post(route('projects.members.store', $project), [
        'employee_id' => employeeOf($target)->id, 'role' => 'member', 'allocation_percent' => 10,
    ])->assertForbidden();
});

test('duplicate member is a validation error', function () {
    [$pm, $project] = pmWithProject();
    $target = employeeOf(userWithRole('employee'));
    $payload = ['employee_id' => $target->id, 'role' => 'member', 'allocation_percent' => 10];

    $this->actingAs($pm)->post(route('projects.members.store', $project), $payload)->assertSessionHasNoErrors();
    $this->actingAs($pm)->post(route('projects.members.store', $project), $payload)->assertSessionHasErrors('employee_id');

    expect(ProjectMember::query()->where('project_id', $project->id)->where('employee_id', $target->id)->count())->toBe(1);
});

test('members cannot be added to an archived project', function () {
    [$pm, $project] = pmWithProject();
    $project->update(['status' => ProjectStatus::Archived]);
    $target = employeeOf(userWithRole('employee'));

    $this->actingAs($pm)->post(route('projects.members.store', $project), [
        'employee_id' => $target->id, 'role' => 'member', 'allocation_percent' => 10,
    ])->assertSessionHasErrors('employee_id');

    expect(ProjectMember::query()->where('project_id', $project->id)->where('employee_id', $target->id)->exists())->toBeFalse();
});
