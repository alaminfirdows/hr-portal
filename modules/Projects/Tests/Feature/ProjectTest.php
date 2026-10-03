<?php

use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Enums\ProjectVisibility;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Shared\Contracts\AuditTrail;

function projectPayload(array $overrides = []): array
{
    return [
        'name' => 'Apollo',
        'visibility' => 'public',
        'priority' => 'medium',
        ...$overrides,
    ];
}

function projectOf(ProjectVisibility $visibility, ?int $managerId = null): Project
{
    return Project::factory()->visibility($visibility)->create(['manager_employee_id' => $managerId]);
}

test('private project is hidden from non-members in list and show', function () {
    $project = projectOf(ProjectVisibility::Private);
    $viewer = userWithRole('employee');

    $this->actingAs($viewer)->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('projects/index')->has('projects.data', 0));

    $this->actingAs($viewer)->get(route('projects.show', $project))->assertNotFound();
});

test('private project is visible to its members', function () {
    $viewer = userWithRole('employee');
    $project = projectOf(ProjectVisibility::Private);
    ProjectMember::factory()->create(['project_id' => $project->id, 'employee_id' => employeeOf($viewer)->id]);

    $this->actingAs($viewer)->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
    $this->actingAs($viewer)->get(route('projects.show', $project))->assertOk();
});

test('restricted project is listed but show is forbidden for non-members', function () {
    $project = projectOf(ProjectVisibility::Restricted);
    $viewer = userWithRole('employee');

    $this->actingAs($viewer)->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name', $project->name)
            ->where('projects.data.0.status', 'active')
            ->where('projects.data.0.can_open', false)
            ->missing('projects.data.0.client')
            ->missing('projects.data.0.description'));

    $this->actingAs($viewer)->get(route('projects.show', $project))->assertForbidden();
});

test('restricted project opens for members and for hr', function () {
    $project = projectOf(ProjectVisibility::Restricted);
    $member = userWithRole('employee');
    ProjectMember::factory()->create(['project_id' => $project->id, 'employee_id' => employeeOf($member)->id]);

    $this->actingAs($member)->get(route('projects.show', $project))->assertOk();
    $this->actingAs(userWithRole('hr'))->get(route('projects.show', $project))->assertOk();
});

test('public project opens for everyone with projects.view', function () {
    $project = projectOf(ProjectVisibility::Public);

    $this->actingAs(userWithRole('employee'))->get(route('projects.show', $project))->assertOk();
});

test('hr sees private projects in the list', function () {
    projectOf(ProjectVisibility::Private);

    $this->actingAs(userWithRole('hr'))->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});

test('archived projects are hidden unless filtered', function () {
    Project::factory()->status(ProjectStatus::Archived)->create();
    $hr = userWithRole('hr');

    $this->actingAs($hr)->get(route('projects.index'))->assertInertia(fn ($page) => $page->has('projects.data', 0));
    $this->actingAs($hr)->get(route('projects.index', ['status' => 'archived']))->assertInertia(fn ($page) => $page->has('projects.data', 1));
});

test('my projects filter lists only projects I belong to', function () {
    $viewer = userWithRole('employee');
    $mine = projectOf(ProjectVisibility::Public);
    projectOf(ProjectVisibility::Public);
    ProjectMember::factory()->create(['project_id' => $mine->id, 'employee_id' => employeeOf($viewer)->id]);

    $this->actingAs($viewer)->get(route('projects.index', ['mine' => 1]))
        ->assertInertia(fn ($page) => $page->has('projects.data', 1)->where('projects.data.0.id', $mine->id));
});

test('project manager creates a project and becomes its manager', function () {
    $pm = userWithRole('project_manager');

    $this->actingAs($pm)->post(route('projects.store'), projectPayload(['budget' => '1500.50']))
        ->assertSessionHasNoErrors();

    $project = Project::query()->where('name', 'Apollo')->firstOrFail();
    expect($project->manager_employee_id)->toBe(employeeOf($pm)->id)
        ->and($project->budget)->toBe(150050)
        ->and($project->code)->toStartWith('PRJ-')
        ->and($project->status)->toBe(ProjectStatus::Planned)
        ->and(ProjectMember::query()->where('project_id', $project->id)->where('employee_id', employeeOf($pm)->id)->exists())->toBeTrue();
});

test('employee cannot create projects', function () {
    $this->actingAs(userWithRole('employee'))->post(route('projects.store'), projectPayload())->assertForbidden();
    $this->actingAs(userWithRole('employee'))->get(route('projects.create'))->assertForbidden();
});

test('project manager edits and archives own project only', function () {
    $pm = userWithRole('project_manager');
    $own = projectOf(ProjectVisibility::Public, employeeOf($pm)->id);
    $other = projectOf(ProjectVisibility::Public);

    $this->actingAs($pm)->put(route('projects.update', $own), projectPayload(['name' => 'Renamed']))
        ->assertSessionHasNoErrors();
    expect($own->fresh()->name)->toBe('Renamed');

    $this->actingAs($pm)->put(route('projects.update', $other), projectPayload(['name' => 'Hijack']))->assertForbidden();
    $this->actingAs($pm)->post(route('projects.archive', $other))->assertForbidden();

    $this->actingAs($pm)->post(route('projects.archive', $own))->assertSessionHasNoErrors();
    expect($own->fresh()->status)->toBe(ProjectStatus::Archived);
});

test('hr can edit any project and changes are audited', function () {
    $project = projectOf(ProjectVisibility::Public);

    $this->actingAs(userWithRole('hr'))->put(route('projects.update', $project), projectPayload(['name' => 'Audited', 'priority' => 'high']))
        ->assertSessionHasNoErrors();

    $actions = collect(app(AuditTrail::class)->forSubject($project->fresh(), 10))->pluck('action');
    expect($actions)->toContain('projects.updated');
});

test('end date cannot precede start date', function () {
    $this->actingAs(userWithRole('hr'))
        ->post(route('projects.store'), projectPayload(['start_date' => '2026-05-10', 'end_date' => '2026-05-01']))
        ->assertSessionHasErrors('end_date');
});

test('guests are redirected', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});
