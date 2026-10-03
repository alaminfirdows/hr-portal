<?php

namespace Modules\Projects;

use Illuminate\Support\Facades\Gate;
use Modules\Projects\Database\Seeders\ProjectsDemoSeeder;
use Modules\Projects\Models\Project;
use Modules\Projects\Policies\ProjectPolicy;
use Modules\Projects\Services\EloquentProjectMembership;
use Modules\Projects\Widgets\ActiveProjectsWidget;
use Modules\Projects\Widgets\MyProjectsWidget;
use Modules\Shared\Contracts\ProjectMembership;
use Modules\Shared\Support\ModuleServiceProvider;
use Modules\Shared\Support\NavItem;

class ProjectsServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Projects';
    }

    public function seedOrder(): int
    {
        return 60;
    }

    public function permissions(): array
    {
        return [
            'projects.view' => 'Browse projects and open the ones you may access',
            'projects.create' => 'Create projects',
            'projects.manage' => 'Edit and archive projects',
            'projects.members.manage' => 'Add, edit and remove project members',
            'projects.fields.manage' => 'Manage project custom fields',
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Projects', 'projects.index', 'folder-kanban', 'projects.view', 'Work', 500, 'projects.index|projects.show|projects.create|projects.edit'),
            new NavItem('Project fields', 'projects.fields.index', 'folders', 'projects.fields.manage', 'Work', 510, 'projects.fields.*'),
        ];
    }

    public function dashboardWidgets(): array
    {
        return [MyProjectsWidget::class, ActiveProjectsWidget::class];
    }

    public function demoSeeders(): array
    {
        return [ProjectsDemoSeeder::class];
    }

    public function notificationTypes(): array
    {
        return ['projects.added' => 'You were added to a project'];
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Project::class, ProjectPolicy::class);
    }

    protected function contracts(): array
    {
        return [ProjectMembership::class => EloquentProjectMembership::class];
    }
}
