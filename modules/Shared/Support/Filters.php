<?php

namespace Modules\Shared\Support;

use Illuminate\Http\Request;

/**
 * Safe reading of list-page query filters. `?search[]=x` style arrays would otherwise reach
 * query builders and string functions and fail with a 500.
 */
final class Filters
{
    /**
     * Only the given keys, only when sent as a plain scalar; arrays and other shapes are dropped.
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public static function only(Request $request, array $keys): array
    {
        $filters = [];

        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_string($value)) {
                $filters[$key] = $value;
            } elseif (is_int($value) || is_float($value)) {
                $filters[$key] = (string) $value;
            }
        }

        return $filters;
    }

    /**
     * Escapes LIKE wildcards. Pair with `escape '!'` in the query.
     */
    public static function escapeLike(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }
}
