<?php

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Notification;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Payroll\Actions\ApprovePayrollRun;
use Modules\Payroll\Actions\CalculatePayrollRun;
use Modules\Payroll\Actions\CreatePayrollRun;
use Modules\Payroll\Actions\MarkReviewed;
use Modules\Payroll\Actions\ProcessPayrollRun;
use Modules\Payroll\Actions\SaveCompensation;
use Modules\Payroll\Enums\PayrollRunStatus;
use Modules\Payroll\Models\Compensation;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\PayrollAdjustment;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Payroll\Notifications\PayslipAvailable;
use Modules\Payroll\Widgets\LatestPayslipWidget;
use Modules\Payroll\Widgets\PayrollStatusWidget;
use Modules\Shared\Contracts\AttendanceSummary;
use Modules\Shared\Contracts\LeaveCalendar;
use Modules\Shared\Exceptions\DomainException;
use Modules\Shared\Exceptions\InvalidStateTransition;
use Modules\Shared\ValueObjects\Money;

const PERIOD = '2026-09';

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00:00'));
});

function paidEmployee(int $basic = 300000, array $attributes = []): Employee
{
    $employee = Employee::factory()->create(['joined_on' => '2025-01-01', ...$attributes]);
    Compensation::factory()->create([
        'employee_id' => $employee->id,
        'basic_salary' => $basic,
        'effective_from' => $employee->joined_on->toDateString(),
    ]);

    return $employee;
}

function newRun(?User $by = null): PayrollRun
{
    return app(CreatePayrollRun::class)->handle(PERIOD, $by ?? userWithRole('hr'));
}

function calculatedRun(?User $by = null): PayrollRun
{
    $run = newRun($by);
    app(CalculatePayrollRun::class)->handle($run, $by);

    return $run->refresh();
}

function publishedPayslipFor(Employee $employee): Payslip
{
    $run = PayrollRun::factory()->create(['status' => PayrollRunStatus::Processed]);

    return Payslip::factory()->published()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id]);
}

// --- Configuration ---------------------------------------------------------------------------

test('hr manages pay components, employees cannot', function () {
    $payload = ['name' => 'Bonus', 'code' => 'bonus', 'type' => 'earning', 'calculation' => 'fixed', 'value' => '150.50'];

    $this->actingAs(userWithRole('employee'))->post(route('payroll.components.store'), $payload)->assertForbidden();

    $hr = userWithRole('hr');
    $this->actingAs($hr)->post(route('payroll.components.store'), $payload)->assertSessionHasNoErrors();

    $component = PayComponent::query()->where('code', 'BONUS')->firstOrFail();
    expect($component->value)->toBe(15050);

    $this->actingAs($hr)->put(route('payroll.components.update', $component), [...$payload, 'value' => '200'])->assertSessionHasNoErrors();
    expect($component->fresh()->value)->toBe(20000);

    $this->actingAs($hr)->delete(route('payroll.components.destroy', $component))->assertSessionHasNoErrors();
    expect(PayComponent::query()->find($component->id))->toBeNull();
});

test('percent component stores basis points and caps at 100', function () {
    $hr = userWithRole('hr');
    $payload = ['name' => 'Rent', 'code' => 'RENT', 'type' => 'earning', 'calculation' => 'percent_of_basic'];

    $this->actingAs($hr)->post(route('payroll.components.store'), [...$payload, 'value' => '50'])->assertSessionHasNoErrors();
    expect(PayComponent::query()->where('code', 'RENT')->value('value'))->toBe(5000);

    $this->actingAs($hr)->post(route('payroll.components.store'), [...$payload, 'code' => 'R2', 'value' => '150'])->assertSessionHasErrors('value');
});

test('formula with unknown variable is rejected on save', function () {
    $this->actingAs(userWithRole('hr'))->post(route('payroll.components.store'), [
        'name' => 'Odd', 'code' => 'ODD', 'type' => 'earning', 'calculation' => 'formula', 'formula' => 'bogus * 2',
    ])->assertSessionHasErrors('formula');

    expect(PayComponent::query()->where('code', 'ODD')->exists())->toBeFalse();
});

test('tax slabs reject overlaps', function () {
    $hr = userWithRole('hr');

    $this->actingAs($hr)->post(route('payroll.tax-slabs.store'), ['min_amount' => '0', 'max_amount' => '1000', 'rate' => '5'])
        ->assertSessionHasNoErrors();
    $this->actingAs($hr)->post(route('payroll.tax-slabs.store'), ['min_amount' => '500', 'max_amount' => '2000', 'rate' => '10'])
        ->assertSessionHasErrors();
});

test('new salary closes the previous compensation the day before', function () {
    $hr = userWithRole('hr');
    $employee = paidEmployee(300000, ['joined_on' => '2025-01-01']);

    $this->actingAs($hr)->post(route('payroll.employees.compensations.store', $employee), [
        'basic_salary' => '4000', 'effective_from' => '2026-07-01', 'reason' => 'Promotion',
    ])->assertSessionHasNoErrors();

    $old = Compensation::query()->where('employee_id', $employee->id)->orderBy('effective_from')->first();
    $new = Compensation::query()->where('employee_id', $employee->id)->orderByDesc('effective_from')->first();

    expect($old->effective_to->toDateString())->toBe('2026-06-30')
        ->and($new->effective_to)->toBeNull()
        ->and($new->basic_salary)->toBe(400000);
});

test('employee cannot change salaries', function () {
    $user = userWithRole('employee');

    $this->actingAs($user)->post(route('payroll.employees.compensations.store', employeeOf($user)), [
        'basic_salary' => '9999', 'effective_from' => '2026-07-01',
    ])->assertForbidden();
});

// --- Run lifecycle ---------------------------------------------------------------------------

test('run goes draft to processed with a different approver', function () {
    Notification::fake();
    $hr = userWithRole('hr');
    $admin = userWithRole('admin');
    paidEmployee();

    $this->actingAs($hr)->post(route('payroll.runs.store'), ['period' => PERIOD])->assertSessionHasNoErrors();
    $run = PayrollRun::query()->firstOrFail();
    expect($run->status)->toBe(PayrollRunStatus::Draft);

    $this->actingAs($hr)->post(route('payroll.runs.calculate', $run))->assertSessionHasNoErrors();
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Calculated)->and($run->fresh()->employee_count)->toBe(1);

    $this->actingAs($hr)->post(route('payroll.runs.review', $run))->assertSessionHasNoErrors();
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Reviewed);

    $this->actingAs($admin)->post(route('payroll.runs.approve', $run))->assertSessionHasNoErrors();
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Approved)->and($run->fresh()->approved_by)->toBe($admin->id);

    $this->actingAs($hr)->post(route('payroll.runs.process', $run))->assertSessionHasNoErrors();
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Processed);
});

test('invalid transitions are rejected', function () {
    $run = newRun();

    expect(fn () => app(MarkReviewed::class)->handle($run, userWithRole('hr')))->toThrow(Exception::class);
    expect(fn () => app(ProcessPayrollRun::class)->handle($run))->toThrow(Exception::class);
    expect($run->fresh()->status)->toBe(PayrollRunStatus::Draft);
});

test('creator cannot approve their own run', function () {
    $hr = userWithRole('hr');
    paidEmployee();
    $run = calculatedRun($hr);
    app(MarkReviewed::class)->handle($run, $hr);

    $this->actingAs($hr)->post(route('payroll.runs.approve', $run->refresh()))->assertSessionHasErrors('approver');

    expect($run->fresh()->status)->toBe(PayrollRunStatus::Reviewed);
    expect(fn () => app(ApprovePayrollRun::class)->handle($run->fresh(), $hr))->toThrow(DomainException::class);
});

test('one run per period and no future months', function () {
    $hr = userWithRole('hr');
    newRun($hr);

    $this->actingAs($hr)->post(route('payroll.runs.store'), ['period' => PERIOD])->assertSessionHasErrors('period');
    $this->actingAs($hr)->post(route('payroll.runs.store'), ['period' => '2027-01'])->assertSessionHasErrors('period');
});

test('processing publishes payslips and notifies each employee', function () {
    Notification::fake();
    $hr = userWithRole('hr');
    $admin = userWithRole('admin');
    $user = userWithRole('employee', ['joined_on' => '2025-01-01']);
    Compensation::factory()->create(['employee_id' => employeeOf($user)->id, 'effective_from' => '2025-01-01']);

    $run = calculatedRun($hr);
    app(MarkReviewed::class)->handle($run, $hr);
    app(ApprovePayrollRun::class)->handle($run->refresh(), $admin);

    expect(Payslip::query()->whereNotNull('published_at')->exists())->toBeFalse();

    app(ProcessPayrollRun::class)->handle($run->refresh(), $admin);

    expect(Payslip::query()->whereNull('published_at')->exists())->toBeFalse();
    Notification::assertSentTo($user, PayslipAvailable::class);
});

test('recalculating replaces payslips instead of duplicating them', function () {
    $hr = userWithRole('hr');
    $employee = paidEmployee(300000);
    $run = calculatedRun($hr);

    expect(Payslip::query()->where('payroll_run_id', $run->id)->count())->toBe(1);
    $firstId = Payslip::query()->firstOrFail()->id;

    Compensation::query()->where('employee_id', $employee->id)->update(['basic_salary' => 500000]);
    $this->actingAs($hr)->post(route('payroll.runs.calculate', $run))->assertSessionHasNoErrors();

    $payslips = Payslip::query()->where('payroll_run_id', $run->id)->get();
    expect($payslips)->toHaveCount(1)
        ->and($payslips->first()->id)->not->toBe($firstId)
        ->and($payslips->first()->basic)->toBe(500000)
        ->and($run->fresh()->employee_count)->toBe(1);
});

test('locked runs cannot be recalculated', function () {
    $hr = userWithRole('hr');
    paidEmployee();
    $run = calculatedRun($hr);
    app(MarkReviewed::class)->handle($run, $hr);
    app(ApprovePayrollRun::class)->handle($run->refresh(), userWithRole('admin'));

    expect(fn () => app(CalculatePayrollRun::class)->handle($run->fresh(), $hr))->toThrow(Exception::class);
});

// --- Calculation rules -----------------------------------------------------------------------

test('employee joining mid month is pro-rated by paid days', function () {
    $hr = userWithRole('hr');
    // September 2026 has 22 working days; 16th to 30th has 11.
    $employee = paidEmployee(300000, ['joined_on' => '2026-09-16']);

    $run = calculatedRun($hr);
    $payslip = Payslip::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->firstOrFail();

    expect($payslip->working_days)->toBe(22)
        ->and((float) $payslip->paid_days)->toBe(11.0)
        ->and($payslip->basic)->toBe(150000);
});

test('formula with unknown variable leaves the run in draft and names the component', function () {
    $hr = userWithRole('hr');
    paidEmployee();
    PayComponent::factory()->formula('mystery_hours * 2')->create(['name' => 'Mystery pay', 'code' => 'MYST']);
    $run = newRun($hr);

    $this->actingAs($hr)->post(route('payroll.runs.calculate', $run))->assertSessionHasErrors('formula');

    expect(session('errors')->first('formula'))->toContain('Mystery pay');

    expect($run->fresh()->status)->toBe(PayrollRunStatus::Draft)
        ->and(Payslip::query()->count())->toBe(0);
});

test('net below zero is clamped and flagged', function () {
    $hr = userWithRole('hr');
    paidEmployee(300000);
    PayComponent::factory()->deduction()->create(['code' => 'LOAN', 'name' => 'Loan', 'value' => 500000]);

    $run = calculatedRun($hr);
    $payslip = Payslip::query()->where('payroll_run_id', $run->id)->firstOrFail();

    expect($payslip->net)->toBe(0)->and($payslip->net_clamped)->toBeTrue();
});

test('overtime and unpaid leave come through the shared contracts', function () {
    app()->instance(AttendanceSummary::class, new class implements AttendanceSummary
    {
        public function forPeriod(int $employeeId, CarbonInterface $from, CarbonInterface $to): array
        {
            return ['present_days' => 20, 'absent_days' => 0, 'late_days' => 0, 'overtime_minutes' => 600];
        }
    });
    app()->instance(LeaveCalendar::class, new class implements LeaveCalendar
    {
        public function isOnLeave(int $employeeId, CarbonInterface $date): bool
        {
            return false;
        }

        public function unpaidLeaveDays(int $employeeId, CarbonInterface $from, CarbonInterface $to): float
        {
            return 2.0;
        }

        public function employeesOnLeave(CarbonInterface $date): array
        {
            return [];
        }
    });

    $hr = userWithRole('hr');
    paidEmployee(2200000);
    PayComponent::factory()->formula('overtime_hours * basic / 22 / 8 * 1.5')->create(['code' => 'OT', 'name' => 'Overtime']);

    $run = calculatedRun($hr);
    $payslip = Payslip::query()->where('payroll_run_id', $run->id)->with('lines')->firstOrFail();

    // 10h * 2,200,000 / 22 / 8 * 1.5 = 187,500; unpaid: 2 of 22 days of basic = 200,000.
    expect($payslip->overtime_minutes)->toBe(600)
        ->and((float) $payslip->unpaid_days)->toBe(2.0)
        ->and($payslip->lines->firstWhere('code', 'OT')?->amount)->toBe(187500)
        ->and($payslip->lines->firstWhere('code', 'UNPAID')?->amount)->toBe(200000);
});

test('one-off adjustments land on the payslip and lock with the run', function () {
    $hr = userWithRole('hr');
    $employee = paidEmployee(300000);

    $this->actingAs($hr)->post(route('payroll.employees.adjustments.store', $employee), [
        'period' => PERIOD, 'type' => 'earning', 'name' => 'Spot bonus', 'amount' => '100',
    ])->assertSessionHasNoErrors();

    $run = calculatedRun($hr);
    $payslip = Payslip::query()->where('payroll_run_id', $run->id)->firstOrFail();
    expect($payslip->gross)->toBe(310000);

    app(MarkReviewed::class)->handle($run, $hr);
    app(ApprovePayrollRun::class)->handle($run->refresh(), userWithRole('admin'));

    $adjustment = PayrollAdjustment::query()->firstOrFail();
    $this->actingAs($hr)->delete(route('payroll.adjustments.destroy', $adjustment))->assertSessionHasErrors();
    expect(PayrollAdjustment::query()->count())->toBe(1);
});

// --- Payslip access --------------------------------------------------------------------------

test('employee sees only their own published payslips', function () {
    $user = userWithRole('employee');
    $mine = publishedPayslipFor(employeeOf($user));
    $other = publishedPayslipFor(Employee::factory()->create());
    Payslip::factory()->create(['employee_id' => employeeOf($user)->id]); // unpublished

    $this->actingAs($user)->get(route('payroll.payslips.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('payroll/payslips/index')->has('payslips', 1)->where('payslips.0.id', $mine->id));

    $this->actingAs($user)->get(route('payroll.payslips.show', $mine))->assertOk();
    $this->actingAs($user)->get(route('payroll.payslips.show', $other))->assertForbidden();
    $this->actingAs($user)->get(route('payroll.payslips.pdf', $other))->assertForbidden();
});

test('unpublished payslips are hidden from the employee but visible to payroll managers', function () {
    $user = userWithRole('employee');
    $draft = Payslip::factory()->create(['employee_id' => employeeOf($user)->id]);

    $this->actingAs($user)->get(route('payroll.payslips.show', $draft))->assertNotFound();
    $this->actingAs(userWithRole('hr'))->get(route('payroll.payslips.show', $draft))->assertOk();
});

test('employee cannot reach payroll management pages', function () {
    $user = userWithRole('employee');

    $this->actingAs($user)->get(route('payroll.runs.index'))->assertForbidden();
    $this->actingAs($user)->get(route('payroll.components.index'))->assertForbidden();
    $this->actingAs($user)->get(route('payroll.employees.index'))->assertForbidden();
});

test('payslip pdf downloads as a pdf', function () {
    $user = userWithRole('employee');
    $payslip = publishedPayslipFor(employeeOf($user));

    $response = $this->actingAs($user)->get(route('payroll.payslips.pdf', $payslip))->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('csv export lists payslips and neutralizes formula cells', function () {
    $hr = userWithRole('hr');
    paidEmployee(300000, ['first_name' => '=HYPERLINK("x")', 'last_name' => 'Evil']);
    $run = calculatedRun($hr);

    $response = $this->actingAs($hr)->get(route('payroll.runs.export', $run))->assertOk();
    $csv = $response->streamedContent();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($csv)->toContain('Employee code')
        ->and($csv)->toContain("'=HYPERLINK")
        ->and($csv)->not->toContain(',=HYPERLINK');

    $this->actingAs(userWithRole('employee'))->get(route('payroll.runs.export', $run))->assertForbidden();
});

test('dashboard widgets show the latest payslip and payroll status', function () {
    $user = userWithRole('employee');
    publishedPayslipFor(employeeOf($user));

    $widget = app(LatestPayslipWidget::class);
    expect($widget->visible($user))->toBeTrue()
        ->and($widget->visible(userWithRole('manager')))->toBeFalse();

    $status = app(PayrollStatusWidget::class);
    expect($status->visible(userWithRole('hr')))->toBeTrue()
        ->and($status->visible($user))->toBeFalse()
        ->and($status->data(userWithRole('hr'))['value'])->toBe('Not started');
});

// --- Integrity: transitions re-check on a locked, fresh row ----------------------------------

test('processing with a stale instance a second time is rejected and notifies once', function () {
    Notification::fake();
    $hr = userWithRole('hr');
    $admin = userWithRole('admin');
    $user = userWithRole('employee', ['joined_on' => '2025-01-01']);
    Compensation::factory()->create(['employee_id' => employeeOf($user)->id, 'effective_from' => '2025-01-01']);

    $run = calculatedRun($hr);
    app(MarkReviewed::class)->handle($run, $hr);
    app(ApprovePayrollRun::class)->handle($run->refresh(), $admin);

    $first = PayrollRun::query()->findOrFail($run->id);
    $stale = PayrollRun::query()->findOrFail($run->id);

    app(ProcessPayrollRun::class)->handle($first, $admin);
    expect(fn () => app(ProcessPayrollRun::class)->handle($stale, $admin))->toThrow(InvalidStateTransition::class);

    Notification::assertSentToTimes($user, PayslipAvailable::class, 1);
});

test('approving with a stale instance after a recalculation is rejected', function () {
    $hr = userWithRole('hr');
    $admin = userWithRole('admin');
    paidEmployee();

    $run = calculatedRun($hr);
    app(MarkReviewed::class)->handle($run, $hr);

    $stale = PayrollRun::query()->findOrFail($run->id);
    app(CalculatePayrollRun::class)->handle(PayrollRun::query()->findOrFail($run->id), $hr);

    expect(fn () => app(ApprovePayrollRun::class)->handle($stale, $admin))->toThrow(InvalidStateTransition::class)
        ->and($run->fresh()->status)->toBe(PayrollRunStatus::Calculated);
});

test('reviewing with a stale instance after approval is rejected', function () {
    $hr = userWithRole('hr');
    $admin = userWithRole('admin');
    paidEmployee();

    $run = calculatedRun($hr);
    $stale = PayrollRun::query()->findOrFail($run->id);
    app(MarkReviewed::class)->handle(PayrollRun::query()->findOrFail($run->id), $hr);
    app(ApprovePayrollRun::class)->handle(PayrollRun::query()->findOrFail($run->id), $admin);

    expect(fn () => app(MarkReviewed::class)->handle($stale, $hr))->toThrow(InvalidStateTransition::class)
        ->and($run->fresh()->status)->toBe(PayrollRunStatus::Approved);
});

test('calculating with a stale instance after approval is rejected', function () {
    $hr = userWithRole('hr');
    $admin = userWithRole('admin');
    paidEmployee();

    $run = calculatedRun($hr);
    app(MarkReviewed::class)->handle(PayrollRun::query()->findOrFail($run->id), $hr);
    $stale = PayrollRun::query()->findOrFail($run->id);
    app(ApprovePayrollRun::class)->handle(PayrollRun::query()->findOrFail($run->id), $admin);

    expect(fn () => app(CalculatePayrollRun::class)->handle($stale, $hr))->toThrow(InvalidStateTransition::class);
});

test('duplicate compensation effective date is a validation error', function () {
    $hr = userWithRole('hr');
    $employee = paidEmployee(300000, ['joined_on' => '2025-01-01']);
    $payload = ['basic_salary' => '4000', 'effective_from' => '2026-07-01'];

    $this->actingAs($hr)->post(route('payroll.employees.compensations.store', $employee), $payload)->assertSessionHasNoErrors();
    $this->actingAs($hr)->post(route('payroll.employees.compensations.store', $employee), $payload)->assertSessionHasErrors('effective_from');

    expect(Compensation::query()->where('employee_id', $employee->id)->count())->toBe(2);
});

test('compensation table enforces one row per employee and effective date', function () {
    $employee = paidEmployee(300000, ['joined_on' => '2025-01-01']);

    expect(fn () => Compensation::factory()->create(['employee_id' => $employee->id, 'effective_from' => '2025-01-01']))
        ->toThrow(QueryException::class);
});

test('saving a compensation with a stale latest row is rejected inside the transaction', function () {
    $employee = paidEmployee(300000, ['joined_on' => '2025-01-01']);
    $action = app(SaveCompensation::class);

    $action->handle($employee, Money::of(400000), CarbonImmutable::parse('2026-07-01'), null);

    expect(fn () => $action->handle($employee, Money::of(500000), CarbonImmutable::parse('2026-06-01'), null))
        ->toThrow(DomainException::class);
});
