<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Actions\ApproveLeaveRequest;
use Modules\Leave\Actions\CancelLeaveRequest;
use Modules\Leave\Actions\RejectLeaveRequest;
use Modules\Leave\Actions\SubmitLeaveRequest;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Events\LeaveRequestApproved;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveDayCalculator;
use Modules\Organization\Models\Holiday;
use Modules\Shared\Contracts\LeaveCalendar;
use Modules\Shared\Exceptions\DomainException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00')); // Monday
});

/**
 * @return array{hr: User, manager: User, staff: User, employee: Employee, type: LeaveType}
 */
function leaveStaff(array $typeAttrs = []): array
{
    $hr = userWithRole('hr');
    $manager = userWithRole('manager');
    $staff = userWithRole('employee', ['manager_id' => employeeOf($manager)->id, 'joined_on' => '2024-01-01']);
    forgetHierarchy();

    $type = LeaveType::factory()->create(['annual_quota' => 10, ...$typeAttrs]);

    return ['hr' => $hr, 'manager' => $manager, 'staff' => $staff, 'employee' => employeeOf($staff), 'type' => $type];
}

function submitLeave(Employee $employee, LeaveType $type, string $start, ?string $end = null, array $extra = []): LeaveRequest
{
    return app(SubmitLeaveRequest::class)->handle($employee, [
        'leave_type_id' => $type->id,
        'start_date' => $start,
        'end_date' => $end ?? $start,
        ...$extra,
    ]);
}

function balanceOf(Employee $employee, LeaveType $type, int $year = 2026): LeaveBalance
{
    return LeaveBalance::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->where('year', $year)->firstOrFail();
}

test('days skip weekends and holidays', function () {
    Holiday::factory()->create(['date' => '2026-03-11', 'is_optional' => false]);
    $calc = app(LeaveDayCalculator::class);

    expect($calc->days(CarbonImmutable::parse('2026-03-09'), CarbonImmutable::parse('2026-03-15')))->toBe(4.0);
});

test('request with zero working days is rejected', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff();

    submitLeave($e, $t, '2026-03-07', '2026-03-08'); // Sat-Sun
})->throws(DomainException::class);

test('half day counts 0.5 and only on single working day', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff();

    $half = submitLeave($e, $t, '2026-03-09', '2026-03-09', ['day_part' => 'first_half']);
    expect($half->days)->toBe(0.5);

    expect(fn () => submitLeave($e, $t, '2026-03-10', '2026-03-11', ['day_part' => 'first_half']))->toThrow(DomainException::class);
});

test('half day refused when type disallows it', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff(['allow_half_day' => false]);

    submitLeave($e, $t, '2026-03-09', '2026-03-09', ['day_part' => 'second_half']);
})->throws(DomainException::class);

test('insufficient balance is refused', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff(['annual_quota' => 2]);

    submitLeave($e, $t, '2026-03-09', '2026-03-11');
})->throws(DomainException::class);

test('submit reserves pending balance', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff();

    $request = submitLeave($e, $t, '2026-03-09', '2026-03-10');

    expect($request->status)->toBe(LeaveStatus::Pending)
        ->and($request->steps)->toBe(['manager', 'hr'])
        ->and(balanceOf($e, $t)->pending)->toBe(2.0)
        ->and(balanceOf($e, $t)->available())->toBe(8.0);
});

test('overlap with pending or approved request is refused', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff();
    submitLeave($e, $t, '2026-03-09', '2026-03-10');

    submitLeave($e, $t, '2026-03-10', '2026-03-11');
})->throws(DomainException::class);

test('max consecutive days is enforced', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff(['max_consecutive_days' => 2]);

    submitLeave($e, $t, '2026-03-09', '2026-03-11');
})->throws(DomainException::class);

test('attachment required when type demands it', function () {
    Storage::fake(config('filesystems.default'));
    ['employee' => $e, 'type' => $t] = leaveStaff(['requires_attachment' => true]);

    expect(fn () => submitLeave($e, $t, '2026-03-09'))->toThrow(DomainException::class);

    $ok = submitLeave($e, $t, '2026-03-10', null, ['attachment' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')]);
    expect($ok->attachment_path)->not->toBeNull();
});

test('unlimited type needs no balance', function () {
    $staff = leaveStaff();
    $unpaid = LeaveType::factory()->unlimited()->create(['is_paid' => false]);

    $request = submitLeave($staff['employee'], $unpaid, '2026-03-09', '2026-03-20');

    expect($request->days)->toBe(10.0);
});

test('flow goes manager then hr and moves pending to used', function () {
    Event::fake([LeaveRequestApproved::class]);
    ['hr' => $hr, 'manager' => $manager, 'employee' => $e, 'type' => $t] = leaveStaff();
    $request = submitLeave($e, $t, '2026-03-09', '2026-03-10');
    $approve = app(ApproveLeaveRequest::class);

    // HR cannot skip ahead: acting on the manager step still leaves the HR step open.
    $approve->handle($request, $manager);
    expect($request->fresh()->status)->toBe(LeaveStatus::Pending)
        ->and($request->fresh()->current_step)->toBe(1);

    $approve->handle($request->fresh(), $hr);

    $fresh = $request->fresh();
    expect($fresh->status)->toBe(LeaveStatus::Approved)
        ->and(balanceOf($e, $t)->pending)->toBe(0.0)
        ->and(balanceOf($e, $t)->used)->toBe(2.0);
    Event::assertDispatched(LeaveRequestApproved::class);
});

test('manager step is skipped when employee has no manager', function () {
    userWithRole('hr');
    $staff = userWithRole('employee', ['manager_id' => null, 'joined_on' => '2024-01-01']);
    $type = LeaveType::factory()->create();

    $request = submitLeave(employeeOf($staff), $type, '2026-03-09');

    expect($request->steps)->toBe(['hr']);
});

test('hr can act on the manager step', function () {
    ['hr' => $hr, 'employee' => $e, 'type' => $t] = leaveStaff();
    $request = submitLeave($e, $t, '2026-03-09');

    app(ApproveLeaveRequest::class)->handle($request, $hr);

    expect($request->fresh()->current_step)->toBe(1);
});

test('manager cannot approve the hr step', function () {
    ['manager' => $manager, 'employee' => $e, 'type' => $t] = leaveStaff(['approval_steps' => ['hr']]);
    $request = submitLeave($e, $t, '2026-03-09');

    app(ApproveLeaveRequest::class)->handle($request, $manager);
})->throws(AuthorizationException::class);

test('manager approving own request is forbidden', function () {
    ['manager' => $manager, 'type' => $t] = leaveStaff();
    $request = submitLeave(employeeOf($manager), $t, '2026-03-09');

    $this->actingAs($manager)->post(route('leave.approvals.approve', $request))->assertForbidden();
});

test('hr cannot approve own request', function () {
    ['hr' => $hr, 'type' => $t] = leaveStaff();
    $request = submitLeave(employeeOf($hr), $t, '2026-03-09');

    $this->actingAs($hr)->post(route('leave.approvals.approve', $request))->assertForbidden();
});

test('unrelated manager cannot approve', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff();
    $other = userWithRole('manager');
    $request = submitLeave($e, $t, '2026-03-09');

    $this->actingAs($other)->post(route('leave.approvals.approve', $request))->assertForbidden();
});

test('reject releases pending balance', function () {
    ['hr' => $hr, 'employee' => $e, 'type' => $t] = leaveStaff();
    $request = submitLeave($e, $t, '2026-03-09', '2026-03-10');

    app(RejectLeaveRequest::class)->handle($request, $hr, 'No cover');

    expect($request->fresh()->status)->toBe(LeaveStatus::Rejected)
        ->and(balanceOf($e, $t)->pending)->toBe(0.0)
        ->and(balanceOf($e, $t)->used)->toBe(0.0);
});

test('decided request cannot be decided again', function () {
    ['hr' => $hr, 'employee' => $e, 'type' => $t] = leaveStaff();
    $request = submitLeave($e, $t, '2026-03-09');
    app(RejectLeaveRequest::class)->handle($request, $hr);

    app(ApproveLeaveRequest::class)->handle($request->fresh(), $hr);
})->throws(DomainException::class);

test('owner cancels pending request and balance is released', function () {
    ['staff' => $staff, 'employee' => $e, 'type' => $t] = leaveStaff();
    $request = submitLeave($e, $t, '2026-03-09');

    app(CancelLeaveRequest::class)->handle($request, $staff);

    expect($request->fresh()->status)->toBe(LeaveStatus::Cancelled)
        ->and(balanceOf($e, $t)->pending)->toBe(0.0);
});

test('cancelling approved future leave restores used balance', function () {
    ['hr' => $hr, 'staff' => $staff, 'employee' => $e, 'type' => $t] = leaveStaff(['approval_steps' => ['hr']]);
    $request = submitLeave($e, $t, '2026-03-09', '2026-03-10');
    app(ApproveLeaveRequest::class)->handle($request, $hr);
    expect(balanceOf($e, $t)->used)->toBe(2.0);

    app(CancelLeaveRequest::class)->handle($request->fresh(), $staff);

    expect($request->fresh()->status)->toBe(LeaveStatus::Cancelled)
        ->and(balanceOf($e, $t)->used)->toBe(0.0);
});

test('cancelling approved leave that already started is refused', function () {
    ['hr' => $hr, 'staff' => $staff, 'employee' => $e, 'type' => $t] = leaveStaff(['approval_steps' => ['hr']]);
    $request = submitLeave($e, $t, '2026-03-02', '2026-03-03');
    app(ApproveLeaveRequest::class)->handle($request, $hr);
    $this->travelTo(CarbonImmutable::parse('2026-03-03 09:00:00'));

    app(CancelLeaveRequest::class)->handle($request->fresh(), $staff);
})->throws(DomainException::class);

test('other employee cannot cancel', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff();
    $stranger = userWithRole('employee');
    $request = submitLeave($e, $t, '2026-03-09');

    $this->actingAs($stranger)->post(route('leave.requests.cancel', $request))->assertForbidden();
});

test('employee submits through http and sees the request', function () {
    ['staff' => $staff, 'type' => $t] = leaveStaff();

    $this->actingAs($staff)->post(route('leave.requests.store'), [
        'leave_type_id' => $t->id,
        'start_date' => '2026-03-09',
        'end_date' => '2026-03-09',
        'day_part' => 'full',
        'reason' => 'Trip',
    ])->assertSessionHasNoErrors();

    $request = LeaveRequest::query()->firstOrFail();
    $this->actingAs($staff)->get(route('leave.requests.show', $request))->assertOk();
    $this->actingAs($staff)->get(route('leave.requests.index'))->assertOk();
});

test('employee cannot view someone else request', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff();
    $request = submitLeave($e, $t, '2026-03-09');

    $this->actingAs(userWithRole('employee'))->get(route('leave.requests.show', $request))->assertForbidden();
});

test('manager sees only team requests in approvals', function () {
    ['manager' => $manager, 'employee' => $e, 'type' => $t] = leaveStaff();
    $outsider = Employee::factory()->create(['joined_on' => '2024-01-01']);
    $mine = submitLeave($e, $t, '2026-03-09');
    $theirs = submitLeave($outsider, $t, '2026-03-09');

    $this->actingAs($manager)->get(route('leave.approvals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('leave/approvals/index')
            ->where('requests.data', fn ($rows) => collect($rows)->pluck('id')->contains($mine->id)
                && ! collect($rows)->pluck('id')->contains($theirs->id)));
});

test('employee cannot open approvals', function () {
    $this->actingAs(userWithRole('employee'))->get(route('leave.approvals.index'))->assertForbidden();
});

test('leave type crud needs leave.manage', function () {
    $payload = ['name' => 'Study', 'code' => 'STD', 'color' => 'blue', 'is_paid' => true, 'requires_attachment' => false,
        'allow_half_day' => false, 'accrual_method' => 'yearly', 'annual_quota' => 5, 'prorate_new_joiners' => true,
        'approval_steps' => ['hr', 'manager'], 'is_active' => true];

    $this->actingAs(userWithRole('employee'))->post(route('leave.types.store'), $payload)->assertForbidden();
    $this->actingAs(userWithRole('hr'))->post(route('leave.types.store'), $payload)->assertSessionHasNoErrors();

    expect(LeaveType::query()->where('code', 'STD')->value('approval_steps'))->toBe(['manager', 'hr']);
});

test('type with requests cannot be deleted', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff();
    submitLeave($e, $t, '2026-03-09');

    $this->actingAs(userWithRole('hr'))->delete(route('leave.types.destroy', $t))->assertSessionHasErrors();

    expect(LeaveType::query()->whereKey($t->id)->exists())->toBeTrue();
});

test('leave calendar contract reports approved leave only', function () {
    ['hr' => $hr, 'employee' => $e, 'type' => $t] = leaveStaff(['approval_steps' => ['hr']]);
    $calendar = app(LeaveCalendar::class);
    $request = submitLeave($e, $t, '2026-03-09', '2026-03-10');

    expect($calendar->isOnLeave($e->id, CarbonImmutable::parse('2026-03-09')))->toBeFalse();

    app(ApproveLeaveRequest::class)->handle($request, $hr);

    expect($calendar->isOnLeave($e->id, CarbonImmutable::parse('2026-03-09')))->toBeTrue()
        ->and($calendar->isOnLeave($e->id, CarbonImmutable::parse('2026-03-11')))->toBeFalse()
        ->and($calendar->employeesOnLeave(CarbonImmutable::parse('2026-03-10')))->toBe([$e->id]);
});

test('unpaid leave days count only unpaid types', function () {
    ['hr' => $hr, 'employee' => $e] = leaveStaff();
    $unpaid = LeaveType::factory()->unlimited()->create(['is_paid' => false, 'approval_steps' => ['hr']]);
    $request = submitLeave($e, $unpaid, '2026-03-09', '2026-03-11', []);
    app(ApproveLeaveRequest::class)->handle($request, $hr);

    expect(app(LeaveCalendar::class)
        ->unpaidLeaveDays($e->id, CarbonImmutable::parse('2026-03-01'), CarbonImmutable::parse('2026-03-31')))->toBe(3.0);
});

test('overlapping request is rejected inside the transaction without reserving balance twice', function () {
    ['employee' => $e, 'type' => $t] = leaveStaff();

    submitLeave($e, $t, '2026-03-10', '2026-03-11');
    expect(fn () => submitLeave($e, $t, '2026-03-11', '2026-03-12'))->toThrow(DomainException::class);

    expect(balanceOf($e, $t)->pending)->toBe(2.0)
        ->and(LeaveRequest::query()->where('employee_id', $e->id)->count())->toBe(1);
});

test('attachment is removed when the submission fails after it was stored', function () {
    Storage::fake(config('filesystems.default'));
    ['employee' => $e, 'type' => $t] = leaveStaff(['requires_attachment' => true]);

    Event::listen('eloquent.created: '.LeaveRequest::class, fn () => throw new RuntimeException('boom'));

    expect(fn () => submitLeave($e, $t, '2026-03-10', null, ['attachment' => UploadedFile::fake()->create('note.pdf', 10, 'application/pdf')]))
        ->toThrow(RuntimeException::class);

    expect(Storage::disk(config('filesystems.default'))->allFiles())->toBe([])
        ->and(LeaveRequest::query()->count())->toBe(0);
});
