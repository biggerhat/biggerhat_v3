<?php

namespace App\Enums;

use App\Interfaces\HasDefaultEnumMethods;
use App\Traits\UsesEnumLabels;
use App\Traits\UsesEnumSelectOptions;

/**
 * Tournament State Machine:
 *
 * Draft — TO creates tournament, configures scenarios/rounds. RSVP open for users.
 * Registration — TO adds players from RSVP list, assigns factions, designates ringer. RSVP still open
 *   so players reaching the link after registration is opened can still express interest.
 * Active (Started) — Scenarios/info/registration locked. Round management enabled.
 *   Round lifecycle: Setup → InProgress (pairings locked, game reporting) → Completed (all games reported)
 * Completed — All rounds finished, tournament finalized.
 */
enum TournamentStatusEnum: string implements HasDefaultEnumMethods
{
    use UsesEnumLabels;
    use UsesEnumSelectOptions;

    case Draft = 'draft';
    case Registration = 'registration';
    case Active = 'active';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Registration => 'Registration',
            self::Active => 'Started',
            self::Completed => 'Completed',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Registration]);
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function isFinished(): bool
    {
        return $this === self::Completed;
    }

    public function allowsRsvp(): bool
    {
        return in_array($this, [self::Draft, self::Registration]);
    }

    public function allowsRegistration(): bool
    {
        return $this === self::Registration;
    }

    public function allowsRoundManagement(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return self[]
     */
    public function validTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Registration],
            self::Registration => [self::Draft, self::Active],
            self::Active => [self::Completed],
            self::Completed => [],
        };
    }
}
