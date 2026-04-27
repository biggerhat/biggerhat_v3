<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Throwable;

/**
 * Central registry of feature flags. Add new flags here. The admin Features
 * page lists everything registered through this provider, so as long as a
 * flag is defined here, super_admin can toggle it from the UI.
 *
 * Pattern: each flag's resolver checks the database-backed global override
 * first (Feature::for(null)) so that admin toggles take effect site-wide.
 * Per-user / per-cohort rollouts can layer their own logic on top.
 */
class FeatureFlagsServiceProvider extends ServiceProvider
{
    /**
     * Registry of defined flags. Keep label + description here so the admin
     * UI can render meaningful copy without hunting through code.
     *
     * @var array<string, array{label: string, description: string, default: bool}>
     */
    public const FLAGS = [
        'beta-tournament-live-updates' => [
            'label' => 'Tournament — Live Updates',
            'description' => 'Live websocket updates on the public tournament view.',
            'default' => true,
        ],
        'experiment-character-comparison' => [
            'label' => 'Character Comparison Tool',
            'description' => 'Side-by-side character stat comparison page.',
            'default' => false,
        ],
    ];

    public function boot(): void
    {
        foreach (self::FLAGS as $name => $meta) {
            $default = $meta['default'];
            Feature::define($name, function () use ($name, $default) {
                // Global override (admin-toggled) wins. Falls back to the registry default.
                return self::resolveGlobal($name, $default);
            });
        }
    }

    /**
     * Read the admin-toggled value for the null (global) scope, defaulting to
     * the registry value when no override exists.
     *
     * Implementation note: we hit the `features` table directly via the DB
     * facade rather than through `Feature::driver()->get()`, because Pennant's
     * driver `get()` is *not* a passive read — when the row is missing it
     * runs the registered resolver (this very method via the closure in boot())
     * and then inserts the result. Calling it from inside the resolver causes
     * unbounded recursion → PHP stack overflow → ERR_EMPTY_RESPONSE on every
     * page load. A direct row lookup avoids that and gives us the same value.
     */
    public static function resolveGlobal(string $name, bool $default): bool
    {
        $stored = self::readStoredOverride($name);

        return $stored ?? $default;
    }

    public static function hasGlobalOverride(string $name): bool
    {
        return self::readStoredOverride($name) !== null;
    }

    /**
     * Pennant serializes `null` scope as the literal string `__laravel_null`
     * (FeatureManager::serializeScope). Stored values are JSON-encoded, so a
     * stored boolean comes back as `"true"` / `"false"`.
     */
    private static function readStoredOverride(string $name): ?bool
    {
        $table = config('pennant.stores.database.table', 'features');
        if (! Schema::hasTable($table)) {
            return null;
        }

        try {
            $row = DB::table($table)
                ->where('name', $name)
                ->where('scope', '__laravel_null')
                ->first();
        } catch (Throwable) {
            return null;
        }

        if (! $row) {
            return null;
        }

        try {
            $value = json_decode($row->value, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_bool($value) ? $value : null;
    }
}
