<?php

use Illuminate\Support\Facades\Artisan;
use Modules\Attendance\Models\Shift;
use Modules\Identity\Models\User;
use Modules\Leave\Models\LeaveType;
use Modules\Payroll\Models\PayComponent;
use Modules\Payroll\Models\TaxSlab;
use Modules\Projects\Models\ProjectFieldDefinition;

it('seeds production reference data without demo content, idempotently', function () {
    config(['app.seed_demo' => false, 'app.initial_admin' => ['name' => 'Root', 'email' => 'root@corp.test', 'password' => 'S3cret-pass!']]);

    Artisan::call('db:seed', ['--force' => true]);
    Artisan::call('db:seed', ['--force' => true]);

    expect(LeaveType::query()->count())->toBe(6)
        ->and(Shift::query()->where('is_default', true)->count())->toBe(1)
        ->and(PayComponent::query()->count())->toBeGreaterThan(0)
        ->and(TaxSlab::query()->count())->toBe(4)
        ->and(ProjectFieldDefinition::query()->count())->toBe(3)
        ->and(User::query()->count())->toBe(1);

    $admin = User::query()->where('email', 'root@corp.test')->firstOrFail();
    expect($admin->hasVerifiedEmail())->toBeTrue()
        ->and($admin->is_active)->toBeTrue()
        ->and($admin->roles->pluck('slug')->all())->toBe(['admin']);
});

it('skips the initial admin without email or password', function () {
    config(['app.seed_demo' => false, 'app.initial_admin' => ['name' => 'Root', 'email' => 'root@corp.test', 'password' => null]]);

    Artisan::call('db:seed', ['--force' => true]);

    expect(User::query()->count())->toBe(0);
});

it('does not overwrite edited leave types on reseed', function () {
    config(['app.seed_demo' => false, 'app.initial_admin' => []]);
    Artisan::call('db:seed', ['--force' => true]);
    LeaveType::query()->where('code', 'AL')->update(['annual_quota' => 30]);

    Artisan::call('db:seed', ['--force' => true]);

    expect(LeaveType::query()->where('code', 'AL')->value('annual_quota'))->toEqual(30);
});

it('adds demo data on top of the base seed', function () {
    config(['app.seed_demo' => true, 'app.initial_admin' => []]);

    Artisan::call('db:seed', ['--force' => true]);

    expect(LeaveType::query()->count())->toBe(6)
        ->and(User::query()->where('email', 'support@example.com')->exists())->toBeTrue();
});
