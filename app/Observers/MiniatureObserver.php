<?php

namespace App\Observers;

use App\Models\Miniature;

class MiniatureObserver
{
    public function created(Miniature $miniature): void
    {
        static::refreshSculptSuffixes($miniature->character_id);
    }

    public function updated(Miniature $miniature): void
    {
        if ($miniature->isDirty('character_id') || $miniature->isDirty('name') || $miniature->isDirty('title') || $miniature->isDirty('version')) {
            static::refreshSculptSuffixes($miniature->character_id);

            if ($miniature->isDirty('character_id')) {
                static::refreshSculptSuffixes($miniature->getOriginal('character_id'));
            }
        }
    }

    public function deleted(Miniature $miniature): void
    {
        static::refreshSculptSuffixes($miniature->character_id);
    }

    /**
     * Recalculate display_name sculpt suffixes for all miniatures belonging to a character.
     *
     * Groups miniatures by their base slug (without -vN suffix). When a group has
     * more than one entry, appends "(Sculpt 2)", "(Sculpt 3)", etc. to distinguish them.
     */
    public static function refreshSculptSuffixes(int $characterId): void
    {
        $miniatures = Miniature::where('character_id', $characterId)
            ->orderBy('id')
            ->get();

        // Group by base slug (strip -v2, -v3, etc.)
        $groups = $miniatures->groupBy(fn (Miniature $m) => preg_replace('/-v\d+$/', '', $m->slug));

        foreach ($groups as $siblings) {
            if ($siblings->count() <= 1) {
                // Single miniature — strip any stale suffix
                $mini = $siblings->first();
                $clean = preg_replace('/ \(Sculpt \d+\)$/', '', $mini->display_name);
                if ($clean !== $mini->display_name) {
                    $mini->updateQuietly(['display_name' => $clean]);
                }

                continue;
            }

            // Multiple sculpts — number them starting at 2 for the second one
            foreach ($siblings->values() as $index => $mini) {
                $base = preg_replace('/ \(Sculpt \d+\)$/', '', $mini->display_name);
                $expected = $index === 0 ? $base : $base.' (Sculpt '.($index + 1).')';

                if ($mini->display_name !== $expected) {
                    $mini->updateQuietly(['display_name' => $expected]);
                }
            }
        }
    }
}
