<?php

namespace Modules\Settings;

use Modules\Settings\Database\Seeders\SettingsSeeder;
use Modules\Settings\Services\SettingsRepository;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class SettingsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Settings';
    }

    public function seedOrder(): int
    {
        return 5;
    }

    public function permissions(): array
    {
        return [
            'settings.manage' => 'Manage organization settings',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Organization settings', 'settings.organization.edit', 'settings', 'settings.manage', 'Administration', 920),
        ];
    }

    public function seeders(): array
    {
        return [SettingsSeeder::class];
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->booted(fn () => $this->applyOrganizationSettings());
    }

    /**
     * Company timezone drives "today" (attendance, leave, schedules); company name drives page titles and mail.
     */
    public function applyOrganizationSettings(): void
    {
        $settings = $this->app->make(SettingsRepository::class)->organization();

        config(['app.timezone' => $settings->timezone, 'app.name' => $settings->companyName]);
        date_default_timezone_set($settings->timezone);
    }

    protected function contracts(): array
    {
        return [SettingsRepository::class => SettingsRepository::class];
    }
}
