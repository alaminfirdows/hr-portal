<?php

use Modules\Projects\Enums\FieldType;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectFieldDefinition;
use Modules\Projects\Models\ProjectFieldValue;

function fieldProjectPayload(array $fields): array
{
    return ['name' => 'Fielded', 'visibility' => 'public', 'priority' => 'low', 'fields' => $fields];
}

test('hr manages field definitions', function () {
    $hr = userWithRole('hr');

    $this->actingAs($hr)->post(route('projects.fields.store'), [
        'key' => 'budget_code', 'label' => 'Budget code', 'type' => 'text', 'is_required' => false,
    ])->assertSessionHasNoErrors();
    $definition = ProjectFieldDefinition::query()->where('key', 'budget_code')->firstOrFail();

    $this->actingAs($hr)->put(route('projects.fields.update', $definition), ['label' => 'Code', 'is_required' => true])
        ->assertSessionHasNoErrors();
    expect($definition->fresh()->label)->toBe('Code')->and($definition->fresh()->is_required)->toBeTrue();

    $this->actingAs($hr)->delete(route('projects.fields.destroy', $definition))->assertSessionHasNoErrors();
    expect(ProjectFieldDefinition::query()->count())->toBe(0);
});

test('field key and type are immutable', function () {
    $definition = ProjectFieldDefinition::factory()->create(['key' => 'fixed', 'type' => FieldType::Text]);

    $this->actingAs(userWithRole('hr'))->put(route('projects.fields.update', $definition), ['label' => 'X', 'key' => 'other', 'type' => 'number'])
        ->assertSessionHasErrors();
    expect($definition->fresh()->key)->toBe('fixed');
});

test('select field requires options', function () {
    $this->actingAs(userWithRole('hr'))->post(route('projects.fields.store'), [
        'key' => 'kind', 'label' => 'Kind', 'type' => 'select',
    ])->assertSessionHasErrors('options');
});

test('field keys are unique', function () {
    ProjectFieldDefinition::factory()->create(['key' => 'dup']);

    $this->actingAs(userWithRole('hr'))->post(route('projects.fields.store'), [
        'key' => 'dup', 'label' => 'Dup', 'type' => 'text',
    ])->assertSessionHasErrors('key');
});

test('only users with projects.fields.manage reach field settings', function () {
    $this->actingAs(userWithRole('employee'))->get(route('projects.fields.index'))->assertForbidden();
    $this->actingAs(userWithRole('project_manager'))->post(route('projects.fields.store'), ['key' => 'a', 'label' => 'A', 'type' => 'text'])->assertForbidden();
    $this->actingAs(userWithRole('hr'))->get(route('projects.fields.index'))->assertOk();
});

test('missing required custom field gives a validation error', function () {
    ProjectFieldDefinition::factory()->required()->create(['key' => 'client_name']);

    $this->actingAs(userWithRole('hr'))->post(route('projects.store'), fieldProjectPayload([]))
        ->assertSessionHasErrors('fields.client_name');
    expect(Project::query()->count())->toBe(0);
});

test('custom field values are validated by type', function () {
    ProjectFieldDefinition::factory()->type(FieldType::Number)->create(['key' => 'seats']);
    ProjectFieldDefinition::factory()->type(FieldType::Date)->create(['key' => 'go_live']);
    ProjectFieldDefinition::factory()->type(FieldType::Url)->create(['key' => 'repo']);
    $hr = userWithRole('hr');

    $this->actingAs($hr)->post(route('projects.store'), fieldProjectPayload(['seats' => 'many', 'go_live' => 'soon', 'repo' => 'nope']))
        ->assertSessionHasErrors(['fields.seats', 'fields.go_live', 'fields.repo']);

    $this->actingAs($hr)->post(route('projects.store'), fieldProjectPayload(['seats' => '12', 'go_live' => '2026-12-01', 'repo' => 'https://example.com/x']))
        ->assertSessionHasNoErrors();
});

test('select field only accepts its options', function () {
    ProjectFieldDefinition::factory()->select(['Internal', 'Client'])->create(['key' => 'kind']);
    $hr = userWithRole('hr');

    $this->actingAs($hr)->post(route('projects.store'), fieldProjectPayload(['kind' => 'Other']))
        ->assertSessionHasErrors('fields.kind');
    $this->actingAs($hr)->post(route('projects.store'), fieldProjectPayload(['kind' => 'Client']))
        ->assertSessionHasNoErrors();
});

test('custom field values are saved, updated and cleared', function () {
    $definition = ProjectFieldDefinition::factory()->create(['key' => 'note']);
    $hr = userWithRole('hr');

    $this->actingAs($hr)->post(route('projects.store'), fieldProjectPayload(['note' => 'first']))->assertSessionHasNoErrors();
    $project = Project::query()->firstOrFail();
    expect(ProjectFieldValue::query()->where('project_id', $project->id)->where('field_definition_id', $definition->id)->value('value'))->toBe('first');

    $payload = ['name' => 'Fielded', 'visibility' => 'public', 'priority' => 'low'];
    $this->actingAs($hr)->put(route('projects.update', $project), [...$payload, 'fields' => ['note' => 'second']])->assertSessionHasNoErrors();
    expect(ProjectFieldValue::query()->where('project_id', $project->id)->value('value'))->toBe('second');

    $this->actingAs($hr)->put(route('projects.update', $project), [...$payload, 'fields' => ['note' => '']])->assertSessionHasNoErrors();
    expect(ProjectFieldValue::query()->where('project_id', $project->id)->count())->toBe(0);
});

test('show page exposes custom fields with values', function () {
    $definition = ProjectFieldDefinition::factory()->create(['key' => 'note', 'label' => 'Note']);
    $project = Project::factory()->create();
    ProjectFieldValue::query()->create(['project_id' => $project->id, 'field_definition_id' => $definition->id, 'value' => 'hello']);

    $this->actingAs(userWithRole('hr'))->get(route('projects.show', $project))
        ->assertInertia(fn ($page) => $page->component('projects/show')->where('fields.0.value', 'hello'));
});
