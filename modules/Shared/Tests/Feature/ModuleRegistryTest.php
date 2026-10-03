<?php

use Modules\Shared\Contracts\WorkCalendar;
use Modules\Shared\Support\ModuleManifest;
use Modules\Shared\Support\ModuleRegistry;
use Modules\Shared\Support\NavItem;

it('collects permissions and ordered navigation from manifests', function () {
    $registry = new ModuleRegistry;
    $registry->register(new ModuleManifest(
        name: 'Alpha', slug: 'alpha', path: '/tmp',
        permissions: ['alpha.view' => 'View alpha'],
        navigation: [new NavItem('Alpha', 'alpha.index', 'box', 'alpha.view', order: 20)],
    ));
    $registry->register(new ModuleManifest(
        name: 'Beta', slug: 'beta', path: '/tmp',
        permissions: ['beta.view' => 'View beta'],
        navigation: [new NavItem('Beta', 'beta.index', 'box', order: 10)],
    ));

    expect(array_keys($registry->permissions()))->toBe(['alpha.view', 'beta.view'])
        ->and($registry->permissions()['alpha.view']['module'])->toBe('Alpha')
        ->and(array_map(fn ($i) => $i->label, $registry->navigation()))->toBe(['Beta', 'Alpha']);
});

it('registers the shared module and binds contracts', function () {
    expect(app(ModuleRegistry::class)->module('Shared'))->not->toBeNull()
        ->and(app(WorkCalendar::class))->toBeInstanceOf(WorkCalendar::class);
});
