<?php

use Illuminate\Support\Facades\Notification;
use Modules\Documents\Enums\DocumentVisibility;
use Modules\Documents\Models\Document;
use Modules\Documents\Models\DocumentShare;
use Modules\Documents\Notifications\DocumentShared;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;
use Modules\Shared\Contracts\ProjectMembership;

function fakeProjects(array $membership): void
{
    app()->instance(ProjectMembership::class, new class($membership) implements ProjectMembership
    {
        /** @param array<int, list<int>> $membership employeeId => projectIds */
        public function __construct(private array $membership) {}

        public function isMember(int $projectId, int $employeeId): bool
        {
            return in_array($projectId, $this->membership[$employeeId] ?? [], true);
        }

        public function projectIdsFor(int $employeeId): array
        {
            return $this->membership[$employeeId] ?? [];
        }

        public function optionsFor(User $user): array
        {
            return [];
        }
    });
}

function visibleIds(User $user): array
{
    return Document::query()->visibleTo($user)->pluck('id')->all();
}

test('visibility matrix', function () {
    $dept = Department::factory()->create();
    $otherDept = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $dept->id]);
    $otherTeam = Team::factory()->create(['department_id' => $otherDept->id]);

    $owner = userWithRole('employee', ['department_id' => $dept->id, 'team_id' => $team->id]);
    $mate = userWithRole('employee', ['department_id' => $dept->id, 'team_id' => $team->id]);
    $outsider = userWithRole('employee', ['department_id' => $otherDept->id, 'team_id' => $otherTeam->id]);

    $private = Document::factory()->visibility(DocumentVisibility::Private)->create(['uploaded_by' => $owner->id]);
    $deptDoc = Document::factory()->visibility(DocumentVisibility::Department)->create(['department_id' => $dept->id]);
    $teamDoc = Document::factory()->visibility(DocumentVisibility::Team)->create(['team_id' => $team->id]);
    $projectDoc = Document::factory()->visibility(DocumentVisibility::Project)->create(['project_id' => 7]);
    $orgDoc = Document::factory()->visibility(DocumentVisibility::Organization)->create();
    $usersDoc = Document::factory()->visibility(DocumentVisibility::Users)->create();
    DocumentShare::query()->create(['document_id' => $usersDoc->id, 'user_id' => $outsider->id, 'shared_by' => $owner->id]);

    fakeProjects([employeeOf($mate)->id => [7]]);

    expect(visibleIds($owner))->toEqualCanonicalizing([$private->id, $deptDoc->id, $teamDoc->id, $orgDoc->id])
        ->and(visibleIds($mate))->toEqualCanonicalizing([$deptDoc->id, $teamDoc->id, $projectDoc->id, $orgDoc->id])
        ->and(visibleIds($outsider))->toEqualCanonicalizing([$orgDoc->id, $usersDoc->id])
        ->and(visibleIds(userWithRole('hr')))->toHaveCount(6);
});

test('employee-attached document is visible to that employee only', function () {
    $subject = userWithRole('employee');
    $other = userWithRole('employee');
    $doc = Document::factory()->visibility(DocumentVisibility::Private)->create(['employee_id' => employeeOf($subject)->id]);

    expect(visibleIds($subject))->toBe([$doc->id])
        ->and(visibleIds($other))->toBe([]);
});

test('department document opened by user in another department is forbidden', function () {
    $dept = Department::factory()->create();
    $other = Department::factory()->create();
    $doc = Document::factory()->file()->visibility(DocumentVisibility::Department)->create(['department_id' => $dept->id]);
    $stranger = userWithRole('employee', ['department_id' => $other->id]);
    $member = userWithRole('employee', ['department_id' => $dept->id]);

    $this->actingAs($stranger)->get(route('documents.show', $doc))->assertForbidden();
    $this->actingAs($stranger)->get(route('documents.download', $doc))->assertForbidden();
    $this->actingAs($member)->get(route('documents.show', $doc))->assertOk();
});

test('index only lists visible documents', function () {
    $user = userWithRole('employee');
    $mine = Document::factory()->create(['title' => 'Everyone']);
    Document::factory()->visibility(DocumentVisibility::Private)->create(['title' => 'Secret']);

    $this->actingAs($user)->get(route('documents.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('documents.data', 1)->where('documents.data.0.id', $mine->id));
});

test('owner shares a document and the recipient is notified once', function () {
    Notification::fake();
    $owner = userWithRole('employee');
    $friend = userWithRole('employee');
    $doc = Document::factory()->visibility(DocumentVisibility::Private)->create(['uploaded_by' => $owner->id]);

    $this->actingAs($friend)->get(route('documents.show', $doc))->assertForbidden();

    $this->actingAs($owner)->post(route('documents.share', $doc), ['user_ids' => [$friend->id]])->assertSessionHasNoErrors();
    $this->actingAs($owner)->post(route('documents.share', $doc), ['user_ids' => [$friend->id]])->assertSessionHasNoErrors();

    Notification::assertSentToTimes($friend, DocumentShared::class, 1);
    $this->actingAs($friend)->get(route('documents.show', $doc))->assertOk();
});

test('non owner cannot share', function () {
    $owner = userWithRole('employee');
    $other = userWithRole('employee');
    $doc = Document::factory()->create(['uploaded_by' => $owner->id]);

    $this->actingAs($other)->post(route('documents.share', $doc), ['user_ids' => []])->assertForbidden();
});

test('employee documents endpoint respects visibility', function () {
    $subject = userWithRole('employee');
    $viewer = userWithRole('employee');
    Document::factory()->visibility(DocumentVisibility::Private)->create(['employee_id' => employeeOf($subject)->id]);

    $this->actingAs($viewer)->getJson(route('documents.employee', employeeOf($subject)))->assertOk()->assertJsonCount(0, 'documents');
    $this->actingAs($subject)->getJson(route('documents.employee', employeeOf($subject)))->assertOk()->assertJsonCount(1, 'documents');
});
