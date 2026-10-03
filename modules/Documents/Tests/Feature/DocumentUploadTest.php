<?php

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Modules\Documents\Enums\DocumentKind;
use Modules\Documents\Enums\DocumentVisibility;
use Modules\Documents\Models\Document;
use Modules\Documents\Notifications\DocumentExpiring;
use Modules\Organization\Models\Department;
use Modules\Shared\Contracts\ProjectMembership;

beforeEach(fn () => Storage::fake(config('filesystems.default')));

function disk(): Filesystem
{
    return Storage::disk(config('filesystems.default'));
}

test('file upload stores on the private disk and records metadata', function () {
    $user = userWithRole('employee');

    $this->actingAs($user)->post(route('documents.store'), [
        'file' => UploadedFile::fake()->create('policy.pdf', 100, 'application/pdf'),
        'title' => 'Policy',
        'visibility' => 'private',
        'tags' => 'hr, policy',
    ])->assertSessionHasNoErrors();

    $doc = Document::query()->firstOrFail();
    expect($doc->kind)->toBe(DocumentKind::File)
        ->and($doc->file_name)->toBe('policy.pdf')
        ->and($doc->tags)->toBe(['hr', 'policy'])
        ->and($doc->uploaded_by)->toBe($user->id)
        ->and($doc->url)->toBeNull();
    disk()->assertExists((string) $doc->file_path);
});

test('upload over 20MB is rejected', function () {
    $this->actingAs(userWithRole('employee'))->post(route('documents.store'), [
        'file' => UploadedFile::fake()->create('big.pdf', 20481, 'application/pdf'),
        'title' => 'Big',
        'visibility' => 'private',
    ])->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

test('disallowed mime is rejected', function () {
    $this->actingAs(userWithRole('employee'))->post(route('documents.store'), [
        'file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'),
        'title' => 'Exe',
        'visibility' => 'private',
    ])->assertSessionHasErrors('file');

    expect(Document::query()->count())->toBe(0);
});

test('external link stores no file', function () {
    $this->actingAs(userWithRole('employee'))->post(route('documents.links.store'), [
        'url' => 'https://example.com/handbook',
        'title' => 'Handbook',
        'visibility' => 'private',
    ])->assertSessionHasNoErrors();

    $doc = Document::query()->firstOrFail();
    expect($doc->kind)->toBe(DocumentKind::Link)
        ->and($doc->file_path)->toBeNull()
        ->and($doc->url)->toBe('https://example.com/handbook')
        ->and(disk()->allFiles())->toBe([]);

    $this->actingAs($doc->uploader)->get(route('documents.download', $doc))->assertForbidden();
});

test('link rejects non http urls', function () {
    $this->actingAs(userWithRole('employee'))->post(route('documents.links.store'), [
        'url' => 'javascript:alert(1)',
        'title' => 'Bad',
        'visibility' => 'private',
    ])->assertSessionHasErrors('url');
});

test('download streams the private file to authorized users only', function () {
    $owner = userWithRole('employee');
    $other = userWithRole('employee');
    disk()->put('documents/a.txt', 'secret');
    $doc = Document::factory()->file('documents/a.txt')->visibility(DocumentVisibility::Private)->create(['uploaded_by' => $owner->id]);

    $this->actingAs($owner)->get(route('documents.download', $doc))->assertOk()->assertDownload('a.txt');
    $this->actingAs($other)->get(route('documents.download', $doc))->assertForbidden();
    auth()->logout();
    $this->get(route('documents.download', $doc))->assertRedirect();
});

test('employee cannot publish to the whole organization', function () {
    $this->actingAs(userWithRole('employee'))->post(route('documents.links.store'), [
        'url' => 'https://example.com',
        'title' => 'Org',
        'visibility' => 'organization',
    ])->assertSessionHasErrors('visibility');

    $this->actingAs(userWithRole('hr'))->post(route('documents.links.store'), [
        'url' => 'https://example.com',
        'title' => 'Org',
        'visibility' => 'organization',
    ])->assertSessionHasNoErrors();
});

test('department visibility defaults to uploader department', function () {
    $dept = Department::factory()->create();
    $user = userWithRole('employee', ['department_id' => $dept->id]);

    $this->actingAs($user)->post(route('documents.links.store'), [
        'url' => 'https://example.com',
        'title' => 'Dept',
        'visibility' => 'department',
    ])->assertSessionHasNoErrors();

    expect(Document::query()->firstOrFail()->department_id)->toBe($dept->id);
});

test('project visibility requires membership', function () {
    $user = userWithRole('employee');
    app()->instance(ProjectMembership::class, new class implements ProjectMembership
    {
        public function isMember(int $projectId, int $employeeId): bool
        {
            return $projectId === 5;
        }

        public function projectIdsFor(int $employeeId): array
        {
            return [5];
        }
    });

    $this->actingAs($user)->post(route('documents.links.store'), [
        'url' => 'https://example.com', 'title' => 'P', 'visibility' => 'project', 'target_project_id' => 9,
    ])->assertSessionHasErrors('target_project_id');

    $this->actingAs($user)->post(route('documents.links.store'), [
        'url' => 'https://example.com', 'title' => 'P', 'visibility' => 'project', 'target_project_id' => 5,
    ])->assertSessionHasNoErrors();
});

test('delete removes the stored file', function () {
    $owner = userWithRole('employee');
    disk()->put('documents/b.txt', 'x');
    $doc = Document::factory()->file('documents/b.txt')->create(['uploaded_by' => $owner->id]);

    $this->actingAs($owner)->delete(route('documents.destroy', $doc))->assertSessionHasNoErrors();

    disk()->assertMissing('documents/b.txt');
    expect(Document::query()->count())->toBe(0);
});

test('expiring documents notify once per tier', function () {
    Notification::fake();
    $owner = userWithRole('employee');
    $doc = Document::factory()->create(['uploaded_by' => $owner->id, 'expires_on' => now()->addDays(20)->toDateString()]);

    $this->artisan('documents:notify-expiring')->assertSuccessful();
    $this->artisan('documents:notify-expiring')->assertSuccessful();
    Notification::assertSentToTimes($owner, DocumentExpiring::class, 1);

    $this->travel(14)->days();
    $this->artisan('documents:notify-expiring')->assertSuccessful();
    $this->artisan('documents:notify-expiring')->assertSuccessful();
    Notification::assertSentToTimes($owner, DocumentExpiring::class, 2);

    expect($doc->fresh()->notified_7_at)->not->toBeNull();
});

test('documents without expiry or far in the future are not notified', function () {
    Notification::fake();
    $owner = userWithRole('employee');
    Document::factory()->create(['uploaded_by' => $owner->id]);
    Document::factory()->create(['uploaded_by' => $owner->id, 'expires_on' => now()->addDays(90)->toDateString()]);

    $this->artisan('documents:notify-expiring')->assertSuccessful();

    Notification::assertNothingSent();
});
