<?php

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Attendance\Actions\CheckIn;
use Modules\Attendance\Actions\CheckOut;
use Modules\Attendance\Actions\FinalizeAttendanceDay;
use Modules\Attendance\Actions\UpsertAttendanceRecord;
use Modules\Attendance\Database\Seeders\AttendanceDemoSeeder;
use Modules\Attendance\Enums\AttendanceSource;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Events\AttendanceRecordEdited;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\EmployeeShift;
use Modules\Attendance\Models\Shift;
use Modules\Audit\Models\AuditLog;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Events\LeaveRequestApproved;
use Modules\Leave\Events\LeaveRequestCancelled;
use Modules\Leave\Models\LeaveRequest;
use Modules\Organization\Models\Holiday;
use Modules\Shared\Contracts\AttendanceSummary;
use Modules\Shared\Exceptions\DomainException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-02 08:55:00')); // Monday
});

function attendee(array $employee = []): array
{
    $user = userWithRole('employee', ['joined_on' => '2024-01-01', ...$employee]);

    return [$user, employeeOf($user)];
}

function recordOf(Employee $employee, string $date): ?AttendanceRecord
{
    return AttendanceRecord::query()->where('employee_id', $employee->id)->whereDate('date', $date)->first();
}

function approvedLeave(Employee $employee, string $start, string $end): LeaveRequest
{
    return LeaveRequest::factory()->create([
        'employee_id' => $employee->id,
        'start_date' => $start,
        'end_date' => $end,
        'status' => 'approved',
    ]);
}

test('check in twice is rejected', function () {
    [, $e] = attendee();
    app(CheckIn::class)->handle($e->id);

    app(CheckIn::class)->handle($e->id);
})->throws(DomainException::class);

test('check out before check in is rejected', function () {
    [, $e] = attendee();

    app(CheckOut::class)->handle($e->id);
})->throws(DomainException::class);

test('check out twice is rejected', function () {
    [, $e] = attendee();
    app(CheckIn::class)->handle($e->id);
    $this->travel(9)->hours();
    app(CheckOut::class)->handle($e->id);

    app(CheckOut::class)->handle($e->id);
})->throws(DomainException::class);

test('on time check in within grace is not late', function () {
    [, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:15:00'));

    $r = app(CheckIn::class)->handle($e->id, '10.0.0.1');

    expect($r->is_late)->toBeFalse()
        ->and($r->late_minutes)->toBe(0)
        ->and($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->source)->toBe(AttendanceSource::Web)
        ->and($r->ip_address)->toBe('10.0.0.1');
});

test('check in after grace is late and counts from shift start', function () {
    [, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:16:00'));

    $r = app(CheckIn::class)->handle($e->id);

    expect($r->is_late)->toBeTrue()->and($r->late_minutes)->toBe(16);
});

test('assigned shift overrides the default', function () {
    [, $e] = attendee();
    Shift::factory()->default()->create();
    $night = Shift::factory()->create(['start_time' => '10:00:00', 'end_time' => '19:00:00']);
    EmployeeShift::query()->create(['employee_id' => $e->id, 'shift_id' => $night->id]);
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:30:00'));

    expect(app(CheckIn::class)->handle($e->id)->is_late)->toBeFalse();
});

test('short day is half day', function () {
    [, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));
    app(CheckIn::class)->handle($e->id);
    $this->travelTo(CarbonImmutable::parse('2026-03-02 12:00:00'));

    $r = app(CheckOut::class)->handle($e->id);

    expect($r->status)->toBe(AttendanceStatus::HalfDay)->and($r->worked_minutes)->toBe(180);
});

test('full day is present with no overtime', function () {
    [, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));
    app(CheckIn::class)->handle($e->id);
    $this->travelTo(CarbonImmutable::parse('2026-03-02 18:20:00'));

    $r = app(CheckOut::class)->handle($e->id);

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and($r->worked_minutes)->toBe(560)
        ->and($r->overtime_minutes)->toBe(0); // 20 min excess is under the 30 min threshold
});

test('overtime counts the whole excess once past the threshold', function () {
    [, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));
    app(CheckIn::class)->handle($e->id);
    $this->travelTo(CarbonImmutable::parse('2026-03-02 19:30:00'));

    $r = app(CheckOut::class)->handle($e->id);

    expect($r->overtime_minutes)->toBe(90);
});

test('finalize writes weekend holiday on leave and absent', function () {
    Holiday::factory()->create(['date' => '2026-03-10', 'is_optional' => false]); // before the calendar memoizes holidays
    [, $present] = attendee();
    [, $absent] = attendee();
    [, $leave] = attendee();
    approvedLeave($leave, '2026-03-02', '2026-03-02');
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));
    app(CheckIn::class)->handle($present->id);

    app(FinalizeAttendanceDay::class)->handle(CarbonImmutable::parse('2026-03-02'));

    expect(recordOf($present, '2026-03-02')->status)->toBe(AttendanceStatus::Present)
        ->and(recordOf($absent, '2026-03-02')->status)->toBe(AttendanceStatus::Absent)
        ->and(recordOf($leave, '2026-03-02')->status)->toBe(AttendanceStatus::OnLeave);

    app(FinalizeAttendanceDay::class)->handle(CarbonImmutable::parse('2026-03-07')); // Saturday
    expect(recordOf($absent, '2026-03-07')->status)->toBe(AttendanceStatus::Weekend);

    app(FinalizeAttendanceDay::class)->handle(CarbonImmutable::parse('2026-03-10'));
    expect(recordOf($absent, '2026-03-10')->status)->toBe(AttendanceStatus::Holiday);
});

test('finalize is idempotent and skips employees not yet joined', function () {
    [, $e] = attendee();
    [, $late] = attendee(['joined_on' => '2026-04-01']);
    $day = CarbonImmutable::parse('2026-03-02');

    $first = app(FinalizeAttendanceDay::class)->handle($day);
    $second = app(FinalizeAttendanceDay::class)->handle($day);

    expect($first)->toBeGreaterThan(0)
        ->and($second)->toBe(0)
        ->and(AttendanceRecord::query()->where('employee_id', $e->id)->count())->toBe(1)
        ->and(recordOf($late, '2026-03-02'))->toBeNull();
});

test('finalize command validates date and runs', function () {
    [, $e] = attendee();

    $this->artisan('attendance:finalize', ['date' => 'nope'])->assertFailed();
    $this->artisan('attendance:finalize', ['date' => '2026-03-02'])->assertSuccessful();

    expect(recordOf($e, '2026-03-02')->status)->toBe(AttendanceStatus::Absent);
});

test('leave approved marks working days up to today as on leave', function () {
    [, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-05 10:00:00')); // Thursday
    $leave = approvedLeave($e, '2026-03-02', '2026-03-09'); // Mon..next Mon

    LeaveRequestApproved::dispatch($leave);

    expect(recordOf($e, '2026-03-02')->status)->toBe(AttendanceStatus::OnLeave)
        ->and(recordOf($e, '2026-03-05')->status)->toBe(AttendanceStatus::OnLeave)
        ->and(recordOf($e, '2026-03-07'))->toBeNull() // weekend
        ->and(recordOf($e, '2026-03-09'))->toBeNull(); // future: finalize does it
});

test('leave approved after finalize corrects absent rows', function () {
    [, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-04 10:00:00'));
    app(FinalizeAttendanceDay::class)->handle(CarbonImmutable::parse('2026-03-03'));
    expect(recordOf($e, '2026-03-03')->status)->toBe(AttendanceStatus::Absent);

    $leave = approvedLeave($e, '2026-03-03', '2026-03-03');
    LeaveRequestApproved::dispatch($leave);

    $row = recordOf($e, '2026-03-03');
    expect($row->status)->toBe(AttendanceStatus::OnLeave)
        ->and(AttendanceRecord::query()->where('employee_id', $e->id)->count())->toBe(1);
});

test('present and manual rows are not overwritten by leave', function () {
    [, $worked] = attendee();
    [, $manual] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));
    app(CheckIn::class)->handle($worked->id);
    AttendanceRecord::factory()->create([
        'employee_id' => $manual->id, 'date' => '2026-03-02', 'check_in_at' => null, 'check_out_at' => null,
        'status' => AttendanceStatus::Absent, 'source' => AttendanceSource::Manual, 'worked_minutes' => 0,
    ]);

    LeaveRequestApproved::dispatch(approvedLeave($worked, '2026-03-02', '2026-03-02'));
    LeaveRequestApproved::dispatch(approvedLeave($manual, '2026-03-02', '2026-03-02'));

    expect(recordOf($worked, '2026-03-02')->status)->toBe(AttendanceStatus::Present)
        ->and(recordOf($manual, '2026-03-02')->status)->toBe(AttendanceStatus::Absent);
});

test('check in replaces a system leave row', function () {
    [, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));
    LeaveRequestApproved::dispatch(approvedLeave($e, '2026-03-02', '2026-03-02'));

    $r = app(CheckIn::class)->handle($e->id);

    expect($r->status)->toBe(AttendanceStatus::Present)
        ->and(AttendanceRecord::query()->where('employee_id', $e->id)->count())->toBe(1);
});

test('leave cancelled removes system leave rows', function () {
    [, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));
    $leave = approvedLeave($e, '2026-03-02', '2026-03-02');
    LeaveRequestApproved::dispatch($leave);
    expect(recordOf($e, '2026-03-02'))->not->toBeNull();

    $leave->update(['status' => 'cancelled']);
    LeaveRequestCancelled::dispatch($leave, true);

    expect(recordOf($e, '2026-03-02'))->toBeNull();
});

test('cancelling a pending request leaves attendance alone', function () {
    [, $e] = attendee();
    AttendanceRecord::factory()->system(AttendanceStatus::OnLeave)->create(['employee_id' => $e->id, 'date' => '2026-03-02']);
    $leave = approvedLeave($e, '2026-03-02', '2026-03-02');

    LeaveRequestCancelled::dispatch($leave, false);

    expect(recordOf($e, '2026-03-02'))->not->toBeNull();
});

test('manual edit is audited and stamps the editor', function () {
    $hr = userWithRole('hr');
    [, $e] = attendee();

    $record = app(UpsertAttendanceRecord::class)->handle($e->id, '2026-03-01', [
        'check_in' => '09:30', 'check_out' => '18:30', 'note' => 'Forgot to check in',
    ], $hr);

    expect($record->source)->toBe(AttendanceSource::Manual)
        ->and($record->edited_by)->toBe($hr->id)
        ->and($record->is_late)->toBeTrue()
        ->and($record->worked_minutes)->toBe(540);

    app(UpsertAttendanceRecord::class)->handle($e->id, '2026-03-01', ['status' => 'absent', 'note' => 'Corrected'], $hr);

    $log = AuditLog::query()->where('action', 'attendance.edited')->latest('id')->first();
    expect(AuditLog::query()->where('action', 'attendance.edited')->count())->toBe(2)
        ->and($log->before['status'])->toBe('present')
        ->and($log->after['status'])->toBe('absent');
    expect(AttendanceRecord::query()->where('employee_id', $e->id)->count())->toBe(1);
});

test('manual edit rejects bad input', function () {
    $hr = userWithRole('hr');
    [, $e] = attendee();
    $upsert = app(UpsertAttendanceRecord::class);

    expect(fn () => $upsert->handle($e->id, '2026-03-01', ['note' => 'x'], $hr))->toThrow(DomainException::class)
        ->and(fn () => $upsert->handle($e->id, '2026-03-01', ['check_in' => '10:00', 'check_out' => '09:00', 'note' => 'x'], $hr))->toThrow(DomainException::class)
        ->and(fn () => $upsert->handle($e->id, '2026-03-09', ['status' => 'absent', 'note' => 'x'], $hr))->toThrow(DomainException::class);
});

test('manual edit needs attendance.manage over the employee', function () {
    $manager = userWithRole('manager');
    [, $e] = attendee();

    app(UpsertAttendanceRecord::class)->handle($e->id, '2026-03-01', ['status' => 'absent', 'note' => 'x'], $manager);
})->throws(AuthorizationException::class);

test('manual edit event is auditable', function () {
    $hr = userWithRole('hr');
    [, $e] = attendee();
    $event = null;
    app('events')->listen(AttendanceRecordEdited::class, function ($e) use (&$event) {
        $event = $e;
    });

    app(UpsertAttendanceRecord::class)->handle($e->id, '2026-03-01', ['status' => 'holiday', 'note' => 'x'], $hr);

    expect($event)->not->toBeNull()->and($event->auditEntry()->action)->toBe('attendance.edited');
});

test('manager sees team records only', function () {
    $manager = userWithRole('manager');
    [, $mine] = attendee(['manager_id' => employeeOf($manager)->id]);
    [, $other] = attendee();
    forgetHierarchy();
    foreach ([$mine, $other] as $emp) {
        AttendanceRecord::factory()->create(['employee_id' => $emp->id, 'date' => '2026-03-02']);
    }

    $this->actingAs($manager)
        ->get(route('attendance.records.index', ['from' => '2026-03-02', 'to' => '2026-03-02']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('attendance/records/index')
            ->has('records.data', 1)
            ->where('records.data.0.employee.id', $mine->id)
            ->where('can_manage', false));
});

test('hr sees everyone and can save an edit over http', function () {
    $hr = userWithRole('hr');
    [, $e] = attendee();

    $this->actingAs($hr)->get(route('attendance.records.index'))->assertOk()->assertInertia(fn ($p) => $p->where('can_manage', true));

    $this->actingAs($hr)->post(route('attendance.records.upsert'), [
        'employee_id' => $e->id, 'date' => '2026-03-02', 'status' => 'absent', 'note' => 'Sick, no notice',
    ])->assertRedirect();

    expect(recordOf($e, '2026-03-02')->status)->toBe(AttendanceStatus::Absent);
});

test('manager cannot post an edit', function () {
    $manager = userWithRole('manager');
    [, $e] = attendee();

    $this->actingAs($manager)->post(route('attendance.records.upsert'), [
        'employee_id' => $e->id, 'date' => '2026-03-02', 'status' => 'absent', 'note' => 'x',
    ])->assertForbidden();
});

test('employee checks in and out over http', function () {
    [$user, $e] = attendee();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));

    $this->actingAs($user)->post(route('attendance.check-in'))->assertRedirect();
    $this->travelTo(CarbonImmutable::parse('2026-03-02 18:00:00'));
    $this->actingAs($user)->post(route('attendance.check-out'))->assertRedirect();
    $this->actingAs($user)->get(route('attendance.my'))->assertOk()
        ->assertInertia(fn ($p) => $p->component('attendance/my')->where('today.check_out', '18:00')->where('stats.present', 1));

    expect(recordOf($e, '2026-03-02')->worked_minutes)->toBe(540);
});

test('second check in over http returns a validation error', function () {
    [$user] = attendee();
    $this->actingAs($user)->post(route('attendance.check-in'));

    $this->actingAs($user)->post(route('attendance.check-in'))->assertSessionHasErrors('attendance');
});

test('employee cannot open records or shifts', function () {
    [$user] = attendee();

    $this->actingAs($user)->get(route('attendance.records.index'))->assertOk(); // own scope
    $this->actingAs($user)->get(route('attendance.shifts.index'))->assertForbidden();
});

test('shift crud keeps one default and assigns employees', function () {
    $hr = userWithRole('hr');
    [, $e] = attendee();
    $first = Shift::factory()->default()->create();

    $this->actingAs($hr)->post(route('attendance.shifts.store'), [
        'name' => 'Early', 'start_time' => '07:00', 'end_time' => '16:00', 'grace_minutes' => 10,
        'half_day_minutes' => 240, 'overtime_after_minutes' => 30, 'is_default' => true, 'is_active' => true,
    ])->assertRedirect();

    $early = Shift::query()->where('name', 'Early')->firstOrFail();
    expect($first->fresh()->is_default)->toBeFalse()->and($early->is_default)->toBeTrue();

    $this->actingAs($hr)->post(route('attendance.shifts.assign', $first), ['employee_ids' => [$e->id]])->assertRedirect();
    $this->actingAs($hr)->post(route('attendance.shifts.assign', $early), ['employee_ids' => [$e->id]])->assertRedirect();
    expect(EmployeeShift::query()->where('employee_id', $e->id)->count())->toBe(1)
        ->and(EmployeeShift::query()->where('employee_id', $e->id)->value('shift_id'))->toBe($early->id);

    $this->actingAs($hr)->get(route('attendance.shifts.index'))->assertOk()->assertInertia(fn ($p) => $p->has('shifts', 2));
    $this->actingAs($hr)->delete(route('attendance.shifts.destroy', $early))->assertSessionHasErrors('shift');
    $this->actingAs($hr)->delete(route('attendance.shifts.destroy', $first))->assertRedirect();
});

test('end time must be after start time', function () {
    $hr = userWithRole('hr');

    $this->actingAs($hr)->post(route('attendance.shifts.store'), [
        'name' => 'Bad', 'start_time' => '18:00', 'end_time' => '09:00', 'grace_minutes' => 0,
        'half_day_minutes' => 0, 'overtime_after_minutes' => 0,
    ])->assertSessionHasErrors('end_time');
});

test('attendance summary feeds payroll totals', function () {
    [, $e] = attendee();
    $base = ['employee_id' => $e->id];
    AttendanceRecord::factory()->create([...$base, 'date' => '2026-02-02', 'is_late' => true, 'late_minutes' => 20]);
    AttendanceRecord::factory()->create([...$base, 'date' => '2026-02-03', 'overtime_minutes' => 60]);
    AttendanceRecord::factory()->system(AttendanceStatus::Absent)->create([...$base, 'date' => '2026-02-04']);
    AttendanceRecord::factory()->system(AttendanceStatus::OnLeave)->create([...$base, 'date' => '2026-02-05']);
    AttendanceRecord::factory()->create([...$base, 'date' => '2026-03-02']); // outside period

    $totals = app(AttendanceSummary::class)->forPeriod($e->id, CarbonImmutable::parse('2026-02-01'), CarbonImmutable::parse('2026-02-28'));

    expect($totals)->toBe(['present_days' => 2, 'absent_days' => 1, 'late_days' => 1, 'overtime_minutes' => 60]);
});

test('dashboard widgets render for employee and hr', function () {
    $hr = userWithRole('hr');
    [$user] = attendee();
    AttendanceRecord::factory()->create(['date' => '2026-03-02', 'is_late' => true, 'late_minutes' => 30]);

    foreach ([$user, $hr] as $who) {
        $this->actingAs($who)->get(route('dashboard'))->assertOk();
    }
});

test('demo seeder builds history', function () {
    userWithRole('hr');
    attendee();

    $this->seed(AttendanceDemoSeeder::class);

    expect(Shift::query()->where('is_default', true)->count())->toBe(1)
        ->and(AttendanceRecord::query()->count())->toBeGreaterThan(40)
        ->and(User::query()->count())->toBeGreaterThan(0);
});
