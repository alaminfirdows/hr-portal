<?php

namespace Modules\Shared\Support;

use Modules\Shared\Contracts\DashboardWidget;

final readonly class ModuleManifest
{
    /**
     * @param  array<string, string>  $permissions
     * @param  list<NavItem>  $navigation
     * @param  list<class-string<DashboardWidget>>  $widgets
     * @param  list<class-string>  $seeders
     * @param  list<class-string>  $demoSeeders
     * @param  array<string, string>  $notificationTypes  type key => description
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $path,
        public array $permissions = [],
        public array $navigation = [],
        public array $widgets = [],
        public array $seeders = [],
        public array $demoSeeders = [],
        public array $notificationTypes = [],
        public int $seedOrder = 100,
    ) {}
}
