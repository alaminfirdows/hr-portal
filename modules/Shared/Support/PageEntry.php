<?php

namespace Modules\Shared\Support;

/**
 * Maps an Inertia component name to its source file for the @vite directive.
 * Mirrors resolvePage() in resources/js/app.tsx: core pages first, then module pages.
 */
final class PageEntry
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function path(string $component): string
    {
        return self::$cache[$component] ??= self::resolve($component);
    }

    private static function resolve(string $component): string
    {
        $core = "resources/js/pages/{$component}.tsx";

        if (is_file(base_path($core))) {
            return $core;
        }

        $matches = glob(base_path("modules/*/resources/js/pages/{$component}.tsx")) ?: [];
        sort($matches);

        return $matches === [] ? $core : ltrim(str_replace(base_path(), '', $matches[0]), '/');
    }
}
