<?php

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Employees\Models\Employee;
use Modules\Identity\Models\User;
use Modules\Leave\Enums\LeaveStatus;
use Modules\Leave\Models\LeaveRequest;
use Modules\Leave\Models\LeaveType;
use Modules\Leave\Notifications\ApprovalReminderNotification;
use Modules\Organization\Models\Department;
use Modules\Payroll\Enums\PayrollRunStatus;
use Modules\Payroll\Models\Compensation;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\Payslip;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectMember;
use Modules\Reports\Services\ReportFilters;
use Modules\Reports\Services\ReportRegistry;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-18 10:00:00'));
});

/**
 * @return list<array<string, mixed>>
 */
function reportRows(string $key, User $user, array $filters = []): array
{
    $report = app(ReportRegistry::class)->find($key);
    $resolved = app(ReportFilters::class)->resolve($filters);

    return $report->query($resolved, $user);
}

function inDepartment(Department $department, array $attrs = []): Employee
{
    return Employee::factory()->create(['department_id' => $department->id, ...$attrs]);
}

// ---- framework -----------------------------------------------------------

test('guests are redirected and employees are forbidden', function () {
    $this->get(route('reports.index'))->assertRedirect(route('login'));

    $employee = userWithRole('employee');
    $this->actingAs($employee)->get(route('reports.index'))->assertForbidden();
    $this->actingAs($employee)->get(route('reports.show', 'hr.headcount'))->assertForbidden();
    $this->actingAs($employee)->get(route('reports.export', 'hr.headcount'))->assertForbidden();
});

test('catalog lists reports grouped, hiding payroll from managers', function () {
    $this->actingAs(userWithRole('hr'))->get(route('reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('reports/index')
            ->where('groups', fn ($g) => collect($g)->pluck('group')->all() === ['HR', 'Attendance', 'Leave', 'Payroll', 'Projects']));

    $this->actingAs(userWithRole('manager'))->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('groups', fn ($g) => ! collect($g)->pluck('group')->contains('Payroll')));
});

test('registry keys are unique and every report renders', function () {
    $registry = app(ReportRegistry::class);
    expect($registry->all())->toHaveCount(11);

    $hr = userWithRole('hr');

    foreach (array_keys($registry->all()) as $key) {
        $this->actingAs($hr)->get(route('reports.show', $key))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('reports/show')->where('report.key', $key));
    }
});

test('unknown report key is 404', function () {
    $hr = userWithRole('hr');

    $this->actingAs($hr)->get(route('reports.show', 'nope.nothing'))->assertNotFound();
    $this->actingAs($hr)->get(route('reports.export', 'nope.nothing'))->assertNotFound();
});

test('payroll reports are forbidden without payroll.manage', function () {
    $manager = userWithRole('manager');
    $employee = userWithRole('employee');

    foreach (['payroll.components', 'payroll.salary_distribution'] as $key) {
        $this->actingAs($manager)->get(route('reports.show', $key))->assertForbidden();
        $this->actingAs($manager)->get(route('reports.export', $key))->assertForbidden();
        $this->actingAs($employee)->get(route('reports.show', $key))->assertForbidden();
    }

    $this->actingAs(userWithRole('hr'))->get(route('reports.show', 'payroll.salary_distribution'))->assertOk();
});

test('empty or invalid date range defaults to the current month', function () {
    $hr = userWithRole('hr');

    $this->actingAs($hr)->get(route('reports.show', 'hr.movements'))
        ->assertInertia(fn ($page) => $page->where('values.from', '2026-03-01')->where('values.to', '2026-03-31'));

    $this->actingAs($hr)->get(route('reports.show', ['key' => 'hr.movements', 'from' => 'garbage', 'to' => '2026-13-45']))
        ->assertInertia(fn ($page) => $page->where('values.from', '2026-03-01')->where('values.to', '2026-03-31'));

    // Reversed range is swapped.
    $this->actingAs($hr)->get(route('reports.show', ['key' => 'hr.movements', 'from' => '2026-02-20', 'to' => '2026-02-10']))
        ->assertInertia(fn ($page) => $page->where('values.from', '2026-02-10')->where('values.to', '2026-02-20'));
});

test('csv export escapes commas, quotes and unicode', function () {
    $hr = userWithRole('hr');
    $dept = Department::factory()->create();
    $record = inDepartment($dept, ['first_name' => 'Zoë "ZZ"', 'last_name' => 'O\'Neil, Jr.']);
    AttendanceRecord::factory()->create(['employee_id' => $record->id, 'date' => '2026-03-10', 'is_late' => true, 'late_minutes' => 20]);
    $formula = inDepartment($dept, ['first_name' => '=SUM(A1)', 'last_name' => 'Evil']);
    AttendanceRecord::factory()->create(['employee_id' => $formula->id, 'date' => '2026-03-11', 'is_late' => true, 'late_minutes' => 5]);

    $response = $this->actingAs($hr)->get(route('reports.export', 'attendance.late'));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    $csv = $response->streamedContent();

    expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue();

    $parsed = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));
    expect($parsed[0])->toBe(['Date', 'Code', 'Employee', 'Department', 'Check-in', 'Minutes late']);

    $names = array_column($parsed, 2);
    expect($names)->toContain('Zoë "ZZ" O\'Neil, Jr.')
        ->and($names)->toContain("'=SUM(A1) Evil"); // formula injection neutralised
    expect(collect($parsed)->every(fn ($row) => count($row) === 6))->toBeTrue();
});

// ---- HR ------------------------------------------------------------------

test('headcount counts people on the payroll at the date per department', function () {
    $hr = userWithRole('hr', ['joined_on' => '2020-01-01']);
    $eng = Department::factory()->create(['name' => 'Engineering']);
    $ops = Department::factory()->create(['name' => 'Ops']);
    inDepartment($eng, ['joined_on' => '2024-01-01']);
    inDepartment($eng, ['joined_on' => '2024-01-01', 'exited_on' => '2026-01-01', 'status' => 'terminated']);
    inDepartment($eng, ['joined_on' => '2026-04-01']); // future joiner
    inDepartment($ops, ['joined_on' => '2024-01-01', 'exited_on' => '2026-03-25']); // leaves after the date

    $rows = collect(reportRows('hr.headcount', $hr, ['to' => '2026-03-18']))->pluck('headcount', 'department');

    expect($rows['Engineering'])->toBe(1)->and($rows['Ops'])->toBe(1);
});

test('joiners, leavers and turnover aggregate for the period', function () {
    $hr = userWithRole('hr', ['joined_on' => '2020-01-01']);
    $dept = Department::factory()->create(['name' => 'Sales']);
    inDepartment($dept, ['joined_on' => '2025-01-01']);
    inDepartment($dept, ['joined_on' => '2025-01-01']);
    inDepartment($dept, ['joined_on' => '2026-03-05']); // joiner
    inDepartment($dept, ['joined_on' => '2025-01-01', 'exited_on' => '2026-03-10', 'status' => 'terminated']); // leaver

    $filters = ['from' => '2026-03-01', 'to' => '2026-03-31'];
    $movement = collect(reportRows('hr.movements', $hr, $filters))->firstWhere('department', 'Sales');
    expect($movement)->toMatchArray(['joiners' => 1, 'leavers' => 1, 'net' => 0]);

    $turnover = collect(reportRows('hr.turnover', $hr, $filters))->firstWhere('department', 'Sales');
    // start 3 (two stayers + leaver), end 3 (two stayers + joiner), avg 3, 1 leaver => 33.3%
    expect($turnover)->toMatchArray(['start' => 3, 'end' => 3, 'leavers' => 1, 'rate' => 33.3]);
});

test('manager sees only their team in reports', function () {
    $manager = userWithRole('manager', ['joined_on' => '2020-01-01']);
    $dept = Department::factory()->create(['name' => 'Platform']);
    $member = Employee::factory()->create(['manager_id' => employeeOf($manager)->id, 'department_id' => $dept->id, 'joined_on' => '2026-03-03']);
    $outsider = Employee::factory()->create(['department_id' => $dept->id, 'joined_on' => '2026-03-04']);
    forgetHierarchy();

    foreach ([$member, $outsider] as $e) {
        AttendanceRecord::factory()->create(['employee_id' => $e->id, 'date' => '2026-03-10', 'is_late' => true, 'late_minutes' => 9]);
    }

    $this->actingAs($manager)->get(route('reports.show', 'attendance.late'))
        ->assertInertia(fn ($page) => $page->where('rows', fn ($rows) => collect($rows)->pluck('code')->all() === [$member->employee_code]));

    // HR sees both.
    $hr = userWithRole('hr');
    expect(reportRows('attendance.late', $hr))->toHaveCount(2);

    $movement = collect(reportRows('hr.movements', $manager, ['from' => '2026-03-01', 'to' => '2026-03-31']))->firstWhere('department', 'Platform');
    expect($movement['joiners'])->toBe(1);
});

// ---- Attendance ----------------------------------------------------------

test('attendance summary aggregates statuses, lateness and overtime', function () {
    $hr = userWithRole('hr');
    $e = Employee::factory()->create();
    $make = fn (string $date, array $attrs) => AttendanceRecord::factory()->create(['employee_id' => $e->id, 'date' => $date, ...$attrs]);
    $make('2026-03-02', ['overtime_minutes' => 90]);
    $make('2026-03-03', ['is_late' => true, 'late_minutes' => 10]);
    $make('2026-03-04', ['status' => 'absent']);
    $make('2026-03-05', ['status' => 'on_leave']);
    $make('2026-03-06', ['status' => 'half_day']);
    $make('2026-02-27', ['overtime_minutes' => 600]); // outside the period

    $row = collect(reportRows('attendance.summary', $hr))->firstWhere('code', $e->employee_code);

    expect($row)->toMatchArray(['present' => 2, 'half_day' => 1, 'late' => 1, 'absent' => 1, 'on_leave' => 1, 'overtime_hours' => 1.5]);
});

// ---- Leave ---------------------------------------------------------------

test('leave usage sums approved days per department and type', function () {
    $hr = userWithRole('hr');
    $dept = Department::factory()->create(['name' => 'Design']);
    $type = LeaveType::factory()->create(['name' => 'Annual']);
    $e = inDepartment($dept);
    $req = fn (string $status, float $days, string $start) => LeaveRequest::factory()->create([
        'employee_id' => $e->id, 'leave_type_id' => $type->id, 'status' => $status, 'days' => $days, 'start_date' => $start, 'end_date' => $start,
    ]);
    $req(LeaveStatus::Approved->value, 2, '2026-03-04');
    $req(LeaveStatus::Approved->value, 1.5, '2026-03-12');
    $req(LeaveStatus::Pending->value, 5, '2026-03-13');
    $req(LeaveStatus::Approved->value, 4, '2026-02-12');

    $row = collect(reportRows('leave.usage', $hr))->firstWhere('department', 'Design');

    expect($row)->toMatchArray(['leave_type' => 'Annual', 'requests' => 2, 'days' => 3.5]);
});

test('leave balances compute remaining days', function () {
    $hr = userWithRole('hr');
    $type = LeaveType::factory()->create(['name' => 'Sick']);
    $e = Employee::factory()->create();
    DB::table('leave_balances')->insert([
        'employee_id' => $e->id, 'leave_type_id' => $type->id, 'year' => 2026,
        'entitled' => 10, 'carried_forward' => 2, 'adjustment' => 1, 'used' => 3, 'pending' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $row = collect(reportRows('leave.balances', $hr, ['year' => 2026]))->firstWhere('code', $e->employee_code);

    expect($row)->toMatchArray(['entitled' => 13.0, 'used' => 3.0, 'pending' => 1.0, 'remaining' => 9.0]);
    expect(reportRows('leave.balances', $hr, ['year' => 2025]))->toBe([]);
});

// ---- Payroll -------------------------------------------------------------

test('payroll component summary totals lines of non-draft runs in range', function () {
    $hr = userWithRole('hr');
    $dept = Department::factory()->create();
    $a = inDepartment($dept);
    $b = inDepartment($dept);
    $run = PayrollRun::factory()->status(PayrollRunStatus::Approved)->create(['period' => '2026-03']);
    $draft = PayrollRun::factory()->status(PayrollRunStatus::Draft)->create(['period' => '2026-02']);

    foreach ([[$a, 100000], [$b, 250000]] as [$emp, $amount]) {
        $slip = Payslip::factory()->create(['payroll_run_id' => $run->id, 'employee_id' => $emp->id]);
        DB::table('payslip_lines')->insert(['payslip_id' => $slip->id, 'code' => 'HRA', 'name' => 'House rent', 'type' => 'earning', 'amount' => $amount, 'sort' => 1]);
    }
    $slip = Payslip::factory()->create(['payroll_run_id' => $draft->id, 'employee_id' => $a->id]);
    DB::table('payslip_lines')->insert(['payslip_id' => $slip->id, 'code' => 'HRA', 'name' => 'House rent', 'type' => 'earning', 'amount' => 999999, 'sort' => 1]);

    $rows = reportRows('payroll.components', $hr, ['from' => '2026-02-01', 'to' => '2026-03-31']);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray(['component' => 'House rent', 'type' => 'Earning', 'employees' => 2, 'total' => 3500.0]);
});

test('salary distribution reports min, average, max per department', function () {
    $hr = userWithRole('hr');
    $dept = Department::factory()->create(['name' => 'Finance']);
    foreach ([200000, 400000] as $salary) {
        Compensation::factory()->create(['employee_id' => inDepartment($dept)->id, 'basic_salary' => $salary]);
    }
    // Superseded compensation must not count.
    Compensation::factory()->create(['employee_id' => inDepartment($dept)->id, 'basic_salary' => 900000, 'effective_from' => '2020-01-01', 'effective_to' => '2021-01-01']);

    $row = collect(reportRows('payroll.salary_distribution', $hr))->firstWhere('department', 'Finance');

    expect($row)->toMatchArray(['employees' => 2, 'min' => 2000.0, 'average' => 3000.0, 'max' => 4000.0, 'total' => 6000.0]);
});

// ---- Projects ------------------------------------------------------------

test('active projects list member counts and exclude inactive ones', function () {
    $hr = userWithRole('hr');
    $project = Project::factory()->create(['name' => 'Atlas', 'status' => 'active']);
    Project::factory()->create(['name' => 'Old', 'status' => 'completed']);
    ProjectMember::factory()->count(3)->create(['project_id' => $project->id]);

    $rows = reportRows('projects.active', $hr);

    expect($rows)->toHaveCount(1)->and($rows[0])->toMatchArray(['name' => 'Atlas', 'members' => 3]);
});

test('workload sums allocation across active projects only', function () {
    $hr = userWithRole('hr');
    $e = Employee::factory()->create();
    ProjectMember::factory()->create(['employee_id' => $e->id, 'project_id' => Project::factory()->create(['status' => 'active']), 'allocation_percent' => 60]);
    ProjectMember::factory()->create(['employee_id' => $e->id, 'project_id' => Project::factory()->create(['status' => 'active']), 'allocation_percent' => 50]);
    ProjectMember::factory()->create(['employee_id' => $e->id, 'project_id' => Project::factory()->create(['status' => 'archived']), 'allocation_percent' => 100]);

    $row = collect(reportRows('projects.workload', $hr))->firstWhere('code', $e->employee_code);

    expect($row)->toMatchArray(['projects' => 2, 'allocation' => 110]);
});

test('project reports are limited to the managers team', function () {
    $manager = userWithRole('manager');
    $mine = Employee::factory()->create(['manager_id' => employeeOf($manager)->id]);
    $other = Employee::factory()->create();
    forgetHierarchy();
    $project = Project::factory()->create(['status' => 'active']);
    ProjectMember::factory()->create(['project_id' => $project->id, 'employee_id' => $mine->id]);
    ProjectMember::factory()->create(['project_id' => $project->id, 'employee_id' => $other->id]);
    $hidden = Project::factory()->create(['status' => 'active']);
    ProjectMember::factory()->create(['project_id' => $hidden->id, 'employee_id' => $other->id]);

    $rows = reportRows('projects.active', $manager);
    expect($rows)->toHaveCount(1)->and($rows[0]['members'])->toBe(1);
    expect(collect(reportRows('projects.workload', $manager))->pluck('code')->all())->toBe([$mine->employee_code]);
});

// ---- Automation ----------------------------------------------------------

test('scheduled commands are all registered', function () {
    $commands = collect(app(Schedule::class)->events())
        ->map(fn (Event $e) => $e->command)
        ->map(fn (?string $c) => (string) preg_replace('/^.*artisan[\'"]?\s+/', '', (string) $c))
        ->all();

    foreach (['leave:allocate', 'leave:accrue', 'leave:remind-approvers', 'attendance:finalize', 'audit:prune'] as $expected) {
        expect(collect($commands)->contains(fn (string $c) => str_starts_with($c, $expected)))->toBeTrue("missing schedule: {$expected}");
    }

    $unique = array_map(fn (string $c) => strtok($c, ' '), $commands);
    expect($unique)->toBe(array_values(array_unique($unique)));
});

test('upcoming birthdays widget lists the next 30 days only', function () {
    $hr = userWithRole('hr');
    Employee::factory()->create(['first_name' => 'Soon', 'last_name' => 'Bday', 'date_of_birth' => '1990-03-25']);
    Employee::factory()->create(['first_name' => 'Later', 'last_name' => 'Bday', 'date_of_birth' => '1990-09-25']);
    Employee::factory()->create(['first_name' => 'Gone', 'last_name' => 'Bday', 'date_of_birth' => '1990-03-25', 'status' => 'terminated']);

    $this->actingAs($hr)->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('widgets', function ($widgets) {
            $w = collect($widgets)->firstWhere('key', 'employees.upcoming_birthdays');
            $titles = collect($w['data']['items'])->pluck('title');

            return $titles->contains('Soon Bday') && ! $titles->contains('Later Bday') && ! $titles->contains('Gone Bday');
        }));
});

test('leave:remind-approvers notifies approvers of requests pending over 2 days', function () {
    Notification::fake();
    $hr = userWithRole('hr');
    $manager = userWithRole('manager');
    $staff = Employee::factory()->create(['manager_id' => employeeOf($manager)->id]);
    forgetHierarchy();

    LeaveRequest::factory()->create(['employee_id' => $staff->id, 'status' => LeaveStatus::Pending, 'steps' => ['manager', 'hr'], 'current_step' => 0, 'created_at' => now()->subDays(3)]);
    LeaveRequest::factory()->create(['employee_id' => $staff->id, 'status' => LeaveStatus::Pending, 'steps' => ['manager', 'hr'], 'current_step' => 0, 'created_at' => now()->subHours(5)]);

    $this->artisan('leave:remind-approvers')->assertSuccessful();

    Notification::assertSentTo($manager, ApprovalReminderNotification::class, fn ($n) => $n->count === 1);
    Notification::assertNotSentTo($hr, ApprovalReminderNotification::class);
});
