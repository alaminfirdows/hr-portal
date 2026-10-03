<?php

use Modules\Shared\Support\PageEntry;

test('core pages resolve to resources/js/pages', function () {
    expect(PageEntry::path('auth/login'))->toBe('resources/js/pages/auth/login.tsx');
});

test('module pages resolve to the owning module', function () {
    expect(PageEntry::path('organization/departments/index'))
        ->toBe('modules/Organization/resources/js/pages/organization/departments/index.tsx');
});

test('every module page is a vite manifest entry when built', function () {
    $manifest = public_path('build/manifest.json');

    if (! is_file($manifest)) {
        $this->markTestSkipped('No build manifest.');
    }

    $keys = array_keys((array) json_decode((string) file_get_contents($manifest), true));

    $pages = collect(glob(base_path('modules/*/resources/js/pages/**/*.tsx')) ?: [])
        ->merge(glob(base_path('modules/*/resources/js/pages/*/*/*.tsx')) ?: [])
        ->map(fn (string $f) => ltrim(str_replace(base_path(), '', $f), '/'))
        ->unique();

    expect($pages)->not->toBeEmpty()
        ->and($pages->diff($keys)->values()->all())->toBe([]);
});
