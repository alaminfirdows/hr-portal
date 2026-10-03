<?php

use Modules\Settings\Services\SettingsRepository;
use Modules\Settings\SettingsServiceProvider;

test('hr can update organization settings', function () {
    $this->actingAs(userWithRole('hr'))->put(route('settings.organization.update'), [
        'company_name' => 'Acme',
        'timezone' => 'Asia/Dhaka',
        'currency' => 'BDT',
        'fiscal_year_start_month' => 7,
        'leave_year_start_month' => 1,
        'working_days' => [7, 1, 2, 3, 4],
        'date_format' => 'd M Y',
    ])->assertSessionHasNoErrors();

    $org = app(SettingsRepository::class)->organization();
    expect($org->companyName)->toBe('Acme')->and($org->workingDays)->toContain(7);
});

test('employee cannot view organization settings', function () {
    $this->actingAs(userWithRole('employee'))->get(route('settings.organization.edit'))->assertForbidden();
});

test('invalid timezone rejected', function () {
    $this->actingAs(userWithRole('admin'))->put(route('settings.organization.update'), [
        'company_name' => 'Acme', 'timezone' => 'Mars/Base', 'currency' => 'USD',
        'fiscal_year_start_month' => 1, 'leave_year_start_month' => 1, 'working_days' => [1], 'date_format' => 'Y-m-d',
    ])->assertSessionHasErrors('timezone');
});

test('company timezone and name are applied to the app', function () {
    app(SettingsRepository::class)->setMany(['timezone' => 'Asia/Dhaka', 'company_name' => 'Acme']);

    app()->getProvider(SettingsServiceProvider::class)?->applyOrganizationSettings();

    expect(config('app.timezone'))->toBe('Asia/Dhaka')
        ->and(now()->getTimezone()->getName())->toBe('Asia/Dhaka')
        ->and(config('app.name'))->toBe('Acme');

    date_default_timezone_set('UTC');
    config(['app.timezone' => 'UTC']);
});
