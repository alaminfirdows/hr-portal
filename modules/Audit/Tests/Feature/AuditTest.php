<?php

use Modules\Audit\Models\AuditLog;
use Modules\Audit\Services\AuditLogger;
use Modules\Employees\Models\Employee;
use Modules\Organization\Models\Department;
use Modules\Shared\Data\AuditEntry;

test('employee change is audited with before and after', function () {
    $hr = userWithRole('hr');
    $e = Employee::factory()->create();
    $dept = Department::factory()->create();

    $this->actingAs($hr)->put(route('employees.update', $e), [
        'first_name' => $e->first_name, 'last_name' => $e->last_name, 'email' => $e->email,
        'joined_on' => $e->joined_on->toDateString(), 'employment_type' => 'full_time', 'work_type' => 'onsite',
        'department_id' => $dept->id,
    ])->assertSessionHasNoErrors();

    $log = AuditLog::query()->where('action', 'employees.updated')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->actor_id)->toBe($hr->id)
        ->and($log->subject_id)->toBe($e->id)
        ->and($log->after['department_id'] ?? null)->toBe($dept->id);
});

test('sensitive keys are redacted', function () {
    $entry = new AuditEntry(action: 'test.secret', description: 'x', after: ['password' => 'p', 'token' => 't', 'name' => 'n']);
    $log = app(AuditLogger::class)->log($entry);

    expect($log->after)->toBe(['password' => '[redacted]', 'token' => '[redacted]', 'name' => 'n']);
});

test('audit log page needs audit.view', function () {
    $this->actingAs(userWithRole('employee'))->get(route('audit.index'))->assertForbidden();
    $this->actingAs(userWithRole('hr'))->get(route('audit.index'))
        ->assertInertia(fn ($page) => $page->component('audit/index')->has('logs.data'));
});

test('prune command removes old entries', function () {
    AuditLog::factory()->create(['created_at' => now()->subYears(3)]);
    AuditLog::factory()->create();

    $this->artisan('audit:prune', ['--days' => 730])->assertSuccessful();

    expect(AuditLog::query()->count())->toBe(1);
});
