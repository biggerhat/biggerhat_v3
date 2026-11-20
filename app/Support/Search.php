<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class Search
{
    /**
     * The apostrophe look-alikes: ' ’ ‘ ‛ ` ´ ʼ ʹ ′ and the full-width form. Card text uses the typographic one (U+2019) while people type a plain
     * one, or none, so a search ignores all of them on both sides. The JavaScript twin is resources/js/lib/searchText.ts (keep the lists in step).
     */
    public const APOSTROPHES = ["'", "\u{2019}", "\u{2018}", "\u{201B}", '`', "\u{00B4}", "\u{02BC}", "\u{02B9}", "\u{2032}", "\u{FF07}"];

    /** The text with its apostrophes taken out: "this action’s damage" -> "this actions damage". */
    public static function stripApostrophes(string $input): string
    {
        return str_replace(self::APOSTROPHES, '', $input);
    }

    /**
     * A column as the search sees it: the column with every apostrophe removed in SQL. Use it only with {@see pattern()}, which strips the
     * typed text the same way, so "action's", "action’s" and "actions" all meet. (Both MySQL and SQLite have REPLACE.)
     */
    public static function column(string $column): Expression
    {
        $sql = DB::connection()->getQueryGrammar()->wrap($column);
        foreach (self::APOSTROPHES as $mark) {
            $sql = 'REPLACE('.$sql.", '".str_replace("'", "''", $mark)."', '')";
        }

        return DB::raw($sql);
    }

    /** The LIKE pattern for typed text, to go with {@see column()}: apostrophes dropped, `*` a wildcard, `%` `_` `\` literal. */
    public static function pattern(string $input): string
    {
        return self::wildcardLike(self::stripApostrophes($input));
    }

    /** Add `whereSearch` / `orWhereSearch` to the query builders (called once from the AppServiceProvider). */
    public static function registerMacros(): void
    {
        \Illuminate\Database\Query\Builder::macro('whereSearch', function (string $column, mixed $input, string $boolean = 'and') {
            /** @var \Illuminate\Database\Query\Builder $this */
            return $this->where(Search::column($column), 'LIKE', Search::pattern((string) $input), $boolean);
        });
        \Illuminate\Database\Query\Builder::macro('orWhereSearch', function (string $column, mixed $input) {
            /** @var \Illuminate\Database\Query\Builder $this */
            return $this->whereSearch($column, $input, 'or');
        });
    }

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
