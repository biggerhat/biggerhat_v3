<?php

namespace App\Enums;

/**
 * Audience a `Games/Show` payload is being built for. Drives the per-context
 * divergences (pick reveal, editor data, scheme intel, observer flag) shared by
 * GameController::show / observe / summary via buildShowProps().
 *
 * Internal to the game render path — deliberately not a user-facing domain enum,
 * so it omits the label/select-option traits the catalog enums carry.
 */
enum GameShowContext
{
    /** The authenticated participant's own live view (full editor + intel). */
    case SelfView;

    /** A public spectator of an observable game (read-only, no editor data). */
    case Observer;

    /** A public post-game summary of a completed / abandoned game. */
    case Summary;

    public function isSelf(): bool
    {
        return $this === self::SelfView;
    }

    /** True for the two public, read-only audiences (observer + summary). */
    public function isPublic(): bool
    {
        return $this !== self::SelfView;
    }
}
