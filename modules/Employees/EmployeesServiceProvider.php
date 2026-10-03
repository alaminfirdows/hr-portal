<?php

namespace Modules\Employees;

use Modules\Employees\Database\Seeders\EmployeesDemoSeeder;
use Modules\Employees\Listeners\ActivateInvitedEmployee;
use Modules\Employees\Models\Employee;
use Modules\Employees\Services\EloquentOrgHierarchy;
use Modules\Employees\Widgets\HeadcountWidget;
use Modules\Employees\Widgets\MyTeamWidget;
use Modules\Employees\Widgets\NewJoinersWidget;
use Modules\Employees\Widgets\UpcomingBirthdaysWidget;
use Modules\Identity\Events\InvitationAccepted;
use Modules\Organization\Models\Department;
use Modules\Organization\Models\Team;
use Modules\Shared\Contracts\OrgHierarchy;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class EmployeesServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Employees';
    }

    public function seedOrder(): int
    {
        return 30;
    }

    public function permissions(): array
    {
        return [
            'directory.view' => 'Browse the employee directory (public profile fields)',
            'employees.view' => 'View full employee records',
            'employees.create' => 'Create employees and send invitations',
            'employees.update' => 'Edit employee records',
            'employees.status' => 'Change employee lifecycle status',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Directory', 'employees.directory', 'contact', 'directory.view', 'People', 200, 'employees.directory*'),
            new NavItem('Employees', 'employees.index', 'users', 'employees.view', 'People', 210, 'employees.index|employees.show|employees.create|employees.edit'),
            new NavItem('My profile', 'employees.profile.edit', 'id-card', null, 'People', 220, 'employees.profile.*'),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [HeadcountWidget::class, NewJoinersWidget::class, MyTeamWidget::class, UpcomingBirthdaysWidget::class];
    }

    public function demoSeeders(): array
    {
        return [EmployeesDemoSeeder::class];
    }

    public function notificationTypes(): array
    {
        return ['employees.profile_changed' => 'HR changed your employee profile'];
    }

    public function register(): void
    {
        // Scoped: memo is reset per request / queued job.
        $this->app->scoped(OrgHierarchy::class, EloquentOrgHierarchy::class);

        parent::register();
    }

    public function boot(): void
    {
        parent::boot();

        // Reporting lines changed: drop memoized lookups.
        $flush = fn () => $this->app->resolved(OrgHierarchy::class)
            ? $this->app->make(OrgHierarchy::class)->flush()
            : null;

        foreach ([Employee::class, Team::class, Department::class] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
        }
    }

    protected function listeners(): array
    {
        return [InvitationAccepted::class => [ActivateInvitedEmployee::class]];
    }
}
