<?php

namespace App\Support;

class Search
{
    /**
     * Turn a user search string into a SQL LIKE pattern with `*` as a
     * user-facing wildcard. Any `%` / `_` / `\` the user typed are
     * escaped first so a query like `100%` doesn't accidentally match
     * everything; the user's `*` then becomes the SQL `%` wildcard.
     *
     * Returns a `%query%` pattern (i.e. always substring-anchored).
     *
     * Always feed the result to a parameterized binding (the normal
     * Eloquent `where('col', 'LIKE', $pattern)` does this), never
     * string-concat into raw SQL.
     */
    public static function wildcardLike(string $input): string
    {
        // Backslash first or it eats the escapes we add next.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $input);

        return '%'.str_replace('*', '%', $escaped).'%';
    }
}
