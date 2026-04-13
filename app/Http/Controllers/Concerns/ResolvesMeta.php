<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Meta;

/**
 * Shared logic for controllers that accept either an existing `meta_id` or a
 * free-text `meta_name`. If `meta_name` is provided we find-or-create the
 * Meta and write its id back to `meta_id`. Used by the player + profile
 * forms so both call sites stay in sync.
 */
trait ResolvesMeta
{
    /**
     * Mutates the validated array in place: if `meta_name` was supplied, it
     * is consumed (removed) and `meta_id` is set to the find-or-created Meta.
     *
     * - `meta_name` empty/missing: no-op
     * - `meta_name` set + `meta_id` not set: find-or-create + assign meta_id
     * - `meta_name` set + `meta_id` already set: meta_id wins (explicit > implicit)
     *
     * @param  array<string, mixed>  $validated
     */
    protected function resolveMetaFromName(array &$validated): void
    {
        if (! array_key_exists('meta_name', $validated)) {
            return;
        }

        $name = $validated['meta_name'];
        unset($validated['meta_name']);

        if (empty($name) || ! empty($validated['meta_id'])) {
            return;
        }

        $validated['meta_id'] = Meta::findOrCreateByName($name)->id;
    }
}
