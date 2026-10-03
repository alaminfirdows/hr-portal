<?php

namespace Modules\Organization;

use Modules\Organization\Database\Seeders\OrganizationDemoSeeder;
use Modules\Organization\Services\CompanyWorkCalendar;
use Modules\Shared\Contracts\WorkCalendar;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class OrganizationServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Organization';
    }

    public function seedOrder(): int
    {
        return 20;
    }

    public function permissions(): array
    {
        return [
            'organization.manage' => 'Manage departments, teams, positions, locations and holidays',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Departments', 'organization.departments.index', 'building-2', 'organization.manage', 'Organization', 300, 'organization.departments.*'),
            new NavItem('Teams', 'organization.teams.index', 'users-round', 'organization.manage', 'Organization', 310, 'organization.teams.*'),
            new NavItem('Positions', 'organization.positions.index', 'briefcase', 'organization.manage', 'Organization', 320, 'organization.positions.*'),
            new NavItem('Locations', 'organization.locations.index', 'map-pin', 'organization.manage', 'Organization', 330, 'organization.locations.*'),
            new NavItem('Holidays', 'organization.holidays.index', 'calendar-days', null, 'Organization', 340, 'organization.holidays.*'),
        ];
    }

    public function demoSeeders(): array
    {
        return [OrganizationDemoSeeder::class];
    }

    protected function contracts(): array
    {
        return [WorkCalendar::class => CompanyWorkCalendar::class];
    }
}
