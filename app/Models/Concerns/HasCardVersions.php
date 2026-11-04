<?php

namespace App\Models\Concerns;

use App\Models\CustomCardVersion;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Keeps the last few previous states of a card so edits can be undone from the editor. Recorded by the card's
 * observer after a save that changed what the card shows; cards the Campaign manages (leaders, totems, crew cards)
 * are excluded because their content is tied to advancement logs that a restore would put out of step.
 */
trait HasCardVersions
{
    public const MAX_VERSIONS = 10;

    /** @return MorphMany<CustomCardVersion, $this> */
    public function versions(): MorphMany
    {
        return $this->morphMany(CustomCardVersion::class, 'versionable')->latest('id');
    }

    /** Columns a version stores and a restore puts back. @return array<int, string> */
    abstract public static function versionedColumns(): array;

    abstract public function isCampaignManaged(): bool;

    /** Call from `updated`: stores the values the saved edit just replaced. */
    public function recordPreviousVersion(): void
    {
        if ($this->isCampaignManaged() || ! $this->wasChanged(static::versionedColumns())) {
            return;
        }

        // Only columns the model actually had loaded (a model built in memory has no DB defaults to put back).
        $original = $this->getOriginal();
        $snapshot = [];
        foreach (static::versionedColumns() as $column) {
            if (! array_key_exists($column, $original)) {
                continue;
            }
            $value = $this->getOriginal($column);
            $snapshot[$column] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        $latest = $this->versions()->first();
        if ($latest && $latest->snapshot == $snapshot) {
            return;
        }

        $this->versions()->create(['snapshot' => $snapshot]);

        $stale = $this->versions()->skip(self::MAX_VERSIONS)->take(100)->pluck('id');
        if ($stale->isNotEmpty()) {
            CustomCardVersion::whereIn('id', $stale)->delete();
        }
    }

    /** Puts a stored version back (a normal update, so the state it replaces becomes a version too and the card re-renders). */
    public function restoreVersion(CustomCardVersion $version): void
    {
        $this->update($version->snapshot);
    }
}
