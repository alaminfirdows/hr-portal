<?php

use Carbon\CarbonImmutable;
use Modules\Employees\Models\Employee;
use Modules\Leave\Actions\AccrueMonthlyBalances;
use Modules\Leave\Actions\AdjustBalance;
use Modules\Leave\Actions\AllocateYearlyBalances;
use Modules\Leave\Enums\AccrualMethod;
use Modules\Leave\Models\LeaveBalance;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Services\LeaveBalanceService;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));
});

test('allocation gives full quota to existing employee', function () {
    $employee = Employee::factory()->create(['joined_on' => '2023-05-01']);
    $type = LeaveType::factory()->create(['annual_quota' => 18]);

    app(AllocateYearlyBalances::class)->handle(2026);

    expect(LeaveBalance::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->value('entitled'))->toBe(18.0);
});

test('allocation is idempotent', function () {
    Employee::factory()->create(['joined_on' => '2023-05-01']);
    LeaveType::factory()->create();
    $action = app(AllocateYearlyBalances::class);

    $first = $action->handle(2026);
    $second = $action->handle(2026);

    expect($first)->toBe(1)->and($second)->toBe(0);
});

test('july joiner is prorated to six twelfths', function () {
    $employee = Employee::factory()->create(['joined_on' => '2026-07-10']);
    $type = LeaveType::factory()->create(['annual_quota' => 12, 'prorate_new_joiners' => true]);

    $balance = app(LeaveBalanceService::class)->balance($employee, $type, 2026);

    expect($balance->entitled)->toBe(6.0);
});

test('proration can be disabled', function () {
    $employee = Employee::factory()->create(['joined_on' => '2026-07-10']);
    $type = LeaveType::factory()->create(['annual_quota' => 12, 'prorate_new_joiners' => false]);

    expect(app(LeaveBalanceService::class)->balance($employee, $type, 2026)->entitled)->toBe(12.0);
});

test('carry forward is capped', function () {
    $employee = Employee::factory()->create(['joined_on' => '2020-01-01']);
    $type = LeaveType::factory()->create(['annual_quota' => 18, 'carry_forward_cap' => 5]);
    LeaveBalance::query()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2025, 'entitled' => 18, 'used' => 4]);

    $balance = app(LeaveBalanceService::class)->balance($employee, $type, 2026);

    expect($balance->carried_forward)->toBe(5.0)->and($balance->available())->toBe(23.0);
});

test('carry forward uses leftover when below cap', function () {
    $employee = Employee::factory()->create(['joined_on' => '2020-01-01']);
    $type = LeaveType::factory()->create(['annual_quota' => 18, 'carry_forward_cap' => 5]);
    LeaveBalance::query()->create(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2025, 'entitled' => 18, 'used' => 16]);

    expect(app(LeaveBalanceService::class)->balance($employee, $type, 2026)->carried_forward)->toBe(2.0);
});

test('monthly accrual adds a twelfth once per month', function () {
    $employee = Employee::factory()->create(['joined_on' => '2020-01-01']);
    $type = LeaveType::factory()->create(['annual_quota' => 12, 'accrual_method' => AccrualMethod::Monthly]);
    $accrue = app(AccrueMonthlyBalances::class);

    $accrue->handle(CarbonImmutable::parse('2026-01-01'));
    $accrue->handle(CarbonImmutable::parse('2026-01-15'));
    $accrue->handle(CarbonImmutable::parse('2026-02-01'));

    $balance = LeaveBalance::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)->firstOrFail();
    expect($balance->entitled)->toBe(2.0)->and($balance->last_accrued_month)->toBe('2026-02');
});

test('monthly accrual commands run', function () {
    Employee::factory()->create(['joined_on' => '2020-01-01']);
    LeaveType::factory()->create(['annual_quota' => 12, 'accrual_method' => AccrualMethod::Monthly]);

    $this->artisan('leave:accrue 2026-03')->assertSuccessful();
    $this->artisan('leave:accrue 2026-13')->assertFailed();
    $this->artisan('leave:allocate 2026')->assertSuccessful();
});

test('unlimited type reports null availability', function () {
    $employee = Employee::factory()->create();
    $type = LeaveType::factory()->unlimited()->create();

    expect(app(LeaveBalanceService::class)->available($employee, $type, 2026))->toBeNull();
});

test('adjustment changes available balance', function () {
    $employee = Employee::factory()->create(['joined_on' => '2020-01-01']);
    $type = LeaveType::factory()->create(['annual_quota' => 10]);

    app(AdjustBalance::class)->handle($employee, $type, 2026, 3.0, 'Bonus day', null);

    expect(app(LeaveBalanceService::class)->available($employee, $type, 2026))->toBe(13.0);
});

test('hr adjusts balance over http, employee cannot', function () {
    $employee = Employee::factory()->create(['joined_on' => '2020-01-01']);
    $type = LeaveType::factory()->create(['annual_quota' => 10]);
    $payload = ['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => 2026, 'delta' => -2, 'reason' => 'Correction'];

    $this->actingAs(userWithRole('employee'))->post(route('leave.balances.adjust'), $payload)->assertForbidden();
    $this->actingAs(userWithRole('hr'))->post(route('leave.balances.adjust'), $payload)->assertSessionHasNoErrors();

    expect(app(LeaveBalanceService::class)->available($employee, $type, 2026))->toBe(8.0);
    $this->actingAs(userWithRole('hr'))->get(route('leave.balances.index'))->assertOk();
});
