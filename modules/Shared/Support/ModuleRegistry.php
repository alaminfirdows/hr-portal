<?php

namespace Modules\Shared\Support;

use Modules\Shared\Contracts\DashboardWidget;

/**
 * Collects what every module contributes: permissions, navigation, widgets, seeders.
 */
final class ModuleRegistry
{
    /** @var array<string, ModuleManifest> */
    private array $modules = [];

    public function register(ModuleManifest $manifest): void
    {
        $this->modules[$manifest->name] = $manifest;
    }

    /**
     * @return list<ModuleManifest>
     */
    public function modules(): array
    {
        return array_values($this->modules);
    }

    public function module(string $name): ?ModuleManifest
    {
        return $this->modules[$name] ?? null;
    }

    /**
     * @return array<string, array{description: string, module: string}>
     */
    public function permissions(): array
    {
        $permissions = [];

        foreach ($this->modules as $module) {
            foreach ($module->permissions as $key => $description) {
                $permissions[$key] = ['description' => $description, 'module' => $module->name];
            }
        }

        ksort($permissions);

        return $permissions;
    }

    /**
     * @return list<NavItem>
     */
    public function navigation(): array
    {
        $items = [];

        foreach ($this->modules as $module) {
            array_push($items, ...$module->navigation);
        }

        usort($items, fn (NavItem $a, NavItem $b) => $a->order <=> $b->order);

        return $items;
    }

    /**
     * @return list<class-string<DashboardWidget>>
     */
    public function widgets(): array
    {
        $widgets = [];

        foreach ($this->modules as $module) {
            array_push($widgets, ...$module->widgets);
        }

        return $widgets;
    }

    /**
     * @return list<class-string>
     */
    public function seeders(bool $demo = false): array
    {
        $seeders = [];
        $modules = $this->modules;
        uasort($modules, fn (ModuleManifest $a, ModuleManifest $b) => $a->seedOrder <=> $b->seedOrder);

        foreach ($modules as $module) {
            array_push($seeders, ...($demo ? $module->demoSeeders : $module->seeders));
        }

        return $seeders;
    }

    /**
     * @return array<string, array{description: string, module: string}>
     */
    public function notificationTypes(): array
    {
        $types = [];

        foreach ($this->modules as $module) {
            foreach ($module->notificationTypes as $key => $description) {
                $types[$key] = ['description' => $description, 'module' => $module->name];
            }
        }

        return $types;
    }
}
