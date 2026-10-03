<?php

use Illuminate\Support\Facades\Notification;
use Modules\Announcements\Enums\Audience;
use Modules\Announcements\Models\Announcement;
use Modules\Announcements\Models\AnnouncementRead;
use Modules\Announcements\Notifications\AnnouncementPublished;
use Modules\Identity\Models\User;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function announcementPayload(array $overrides = []): array
{
    return [
        'title' => 'Hello',
        'body' => 'World',
        'priority' => 'normal',
        'audience' => 'all',
        ...$overrides,
    ];
}

function feedIds(User $user): array
{
    return Announcement::query()->published()->visibleTo($user)->pluck('id')->all();
}

test('audience targeting by department team and employee', function () {
    $dept = Department::factory()->create();
    $other = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $dept->id]);

    $inDept = userWithRole('employee', ['department_id' => $dept->id, 'team_id' => $team->id]);
    $outside = userWithRole('employee', ['department_id' => $other->id]);

    $all = Announcement::factory()->create();
    $deptA = Announcement::factory()->create(['audience' => Audience::Department]);
    $deptA->targets()->create(['target_id' => $dept->id]);
    $teamA = Announcement::factory()->create(['audience' => Audience::Team]);
    $teamA->targets()->create(['target_id' => $team->id]);
    $empA = Announcement::factory()->create(['audience' => Audience::Employees]);
    $empA->targets()->create(['target_id' => employeeOf($outside)->id]);

    expect(feedIds($inDept))->toEqualCanonicalizing([$all->id, $deptA->id, $teamA->id])
        ->and(feedIds($outside))->toEqualCanonicalizing([$all->id, $empA->id]);
});

test('future scheduled announcement is hidden and not notified until publish time', function () {
    Notification::fake();
    $hr = userWithRole('hr');
    $employee = userWithRole('employee');

    $this->actingAs($hr)->post(route('announcements.store'), announcementPayload([
        'publish_at' => now()->addDay()->toDateTimeString(),
    ]))->assertSessionHasNoErrors();

    $announcement = Announcement::query()->firstOrFail();
    expect(feedIds($employee))->toBe([]);
    Notification::assertNothingSent();
    $this->actingAs($employee)->get(route('announcements.show', $announcement))->assertForbidden();

    $this->artisan('announcements:dispatch')->assertSuccessful();
    Notification::assertNothingSent();

    $this->travel(25)->hours();

    expect(feedIds($employee))->toBe([$announcement->id]);
    $this->artisan('announcements:dispatch')->assertSuccessful();
    Notification::assertSentTo($employee, AnnouncementPublished::class);
});

test('due announcement notifies immediately and dispatch runs once', function () {
    Notification::fake();
    $hr = userWithRole('hr');
    $employee = userWithRole('employee');

    $this->actingAs($hr)->post(route('announcements.store'), announcementPayload())->assertSessionHasNoErrors();

    Notification::assertSentToTimes($employee, AnnouncementPublished::class, 1);

    $this->artisan('announcements:dispatch')->assertSuccessful();
    $this->artisan('announcements:dispatch')->assertSuccessful();

    Notification::assertSentToTimes($employee, AnnouncementPublished::class, 1);
});

test('only the targeted audience is notified', function () {
    Notification::fake();
    $dept = Department::factory()->create();
    $member = userWithRole('employee', ['department_id' => $dept->id]);
    $outsider = userWithRole('employee');

    $this->actingAs(userWithRole('hr'))->post(route('announcements.store'), announcementPayload([
        'audience' => 'department',
        'target_ids' => [$dept->id],
    ]))->assertSessionHasNoErrors();

    Notification::assertSentTo($member, AnnouncementPublished::class);
    Notification::assertNotSentTo($outsider, AnnouncementPublished::class);
});

test('expired announcement leaves the dashboard but stays in the hr list', function () {
    $employee = userWithRole('employee');
    $live = Announcement::factory()->create(['title' => 'Live']);
    $expired = Announcement::factory()->create(['title' => 'Old', 'expires_at' => now()->subHour()]);

    expect(feedIds($employee))->toBe([$live->id]);

    $this->actingAs($employee)->get(route('dashboard'))
        ->assertInertia(function ($page) use ($live, $expired) {
            $widget = collect($page->toArray()['props']['widgets'])->firstWhere('key', 'announcements.recent');
            $ids = collect($widget['data']['items'] ?? [])->pluck('id');
            expect($ids->all())->toBe([$live->id])->and($ids->contains($expired->id))->toBeFalse();

            return $page;
        });

    $this->actingAs(userWithRole('hr'))->get(route('announcements.manage'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('announcements.data', 2)
            ->where('announcements.data', fn ($rows) => collect($rows)->firstWhere('id', $expired->id)['status'] === 'expired'));
});

test('read tracking is recorded once per user', function () {
    $employee = userWithRole('employee');
    $announcement = Announcement::factory()->create();

    $this->actingAs($employee)->get(route('announcements.index'))
        ->assertInertia(fn ($page) => $page->where('unread', 1));

    $this->actingAs($employee)->get(route('announcements.show', $announcement))->assertOk();
    $this->actingAs($employee)->get(route('announcements.show', $announcement))->assertOk();

    expect(AnnouncementRead::query()->where('user_id', $employee->id)->count())->toBe(1);
    $this->actingAs($employee)->get(route('announcements.index'))
        ->assertInertia(fn ($page) => $page->where('unread', 0));
});

test('announcement not addressed to the user is forbidden', function () {
    $dept = Department::factory()->create();
    $announcement = Announcement::factory()->create(['audience' => Audience::Department]);
    $announcement->targets()->create(['target_id' => $dept->id]);

    $this->actingAs(userWithRole('employee'))->get(route('announcements.show', $announcement))->assertForbidden();
});

test('hr previewing a scheduled announcement does not mark it read', function () {
    $hr = userWithRole('hr');
    $announcement = Announcement::factory()->create(['publish_at' => now()->addDay()]);

    $this->actingAs($hr)->get(route('announcements.show', $announcement))->assertOk();

    expect(AnnouncementRead::query()->count())->toBe(0);
});

test('employee cannot manage announcements', function () {
    $employee = userWithRole('employee');
    $announcement = Announcement::factory()->create();

    $this->actingAs($employee)->get(route('announcements.manage'))->assertForbidden();
    $this->actingAs($employee)->post(route('announcements.store'), announcementPayload())->assertForbidden();
    $this->actingAs($employee)->delete(route('announcements.destroy', $announcement))->assertForbidden();
});

test('validation: expiry after publish and targets required', function () {
    $hr = userWithRole('hr');

    $this->actingAs($hr)->post(route('announcements.store'), announcementPayload([
        'publish_at' => now()->addDays(2)->toDateTimeString(),
        'expires_at' => now()->addDay()->toDateTimeString(),
    ]))->assertSessionHasErrors('expires_at');

    $this->actingAs($hr)->post(route('announcements.store'), announcementPayload(['audience' => 'team']))
        ->assertSessionHasErrors('target_ids');
});

test('hr updates and deletes an announcement', function () {
    $hr = userWithRole('hr');
    $announcement = Announcement::factory()->create();

    $this->actingAs($hr)->put(route('announcements.update', $announcement), announcementPayload(['title' => 'Changed']))
        ->assertSessionHasNoErrors();
    expect($announcement->fresh()?->title)->toBe('Changed');

    $this->actingAs($hr)->delete(route('announcements.destroy', $announcement))->assertSessionHasNoErrors();
    expect(Announcement::query()->count())->toBe(0);
});
