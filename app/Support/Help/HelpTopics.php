<?php

namespace App\Support\Help;

use App\Models\User;
use App\Support\Tabletop\TabletopAccess;

/**
 * Which help topics exist, in the order they are listed, and who may open each. The text itself lives in the front end
 * (resources/js/lib/help/topics/<slug>.ts); this only decides what is visible, so a topic for a pre-release feature is a 404 until the
 * feature is. A topic added here needs its file there (the node test in resources/js/lib/help/help.test.mjs and HelpTest check both sides).
 */
class HelpTopics
{
    /** @var list<string> */
    public const SLUGS = ['crew-builder', 'game-tracker', 'collection', 'tabletop'];

    /** @return list<string> */
    public static function visibleTo(?User $user): array
    {
        return array_values(array_filter(self::SLUGS, fn (string $slug) => self::allows($slug, $user)));
    }

    public static function allows(string $slug, ?User $user): bool
    {
        return match ($slug) {
            // Pre-release: the tabletop's help is hidden (404) exactly as the tabletop is.
            'tabletop' => TabletopAccess::allows($user),
            'crew-builder', 'game-tracker', 'collection' => true,
            default => false,
        };
    }
}
