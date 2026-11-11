<?php

namespace App\Tournaments\Rulesets;

use App\Tournaments\ScoringConfig;

/**
 * A tournament format for some game: scoring parameters, standings tiebreakers,
 * and event-planning defaults. Built from config/tournaments.php — there is no
 * per-game subclass; a new game is a new config entry.
 */
final class Ruleset
{
    /**
     * @param  list<string>  $tiebreakers  ordered keys applied after TP: 'sos', 'diff', 'vp'
     * @param  bool  $tiebreakerToggle  whether the TO may switch between the legacy tiebreaker modes
     * @param  list<array{0: int, 1: int|null, 2: int}>  $suggestedRounds  [min players, max players|null, rounds]
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $gameSystem,
        public readonly ScoringConfig $scoring,
        public readonly array $tiebreakers,
        public readonly bool $tiebreakerToggle = false,
        public readonly array $suggestedRounds = [],
        public readonly ?int $defaultRoundTimeLimit = null,
        /** @var array{label: string, unit: string, default: int, min: int, max: int, step: int} $gameSize */
        public readonly array $gameSize = ['label' => 'Encounter Size (SS)', 'unit' => 'ss', 'default' => 50, 'min' => 20, 'max' => 100, 'step' => 5],
        /** Whether the Malifaux-style season / encounter-type pickers apply (removed once scenarios are data-driven, Phase 4). */
        public readonly bool $usesSeason = true,
        public readonly bool $usesEncounterType = true,
        /**
         * Components a player's score is entered as; their sum is the player's VP. Order is display order.
         *
         * @var list<array{key: string, label: string, min: int, max: int}> $scoreFields
         */
        public readonly array $scoreFields = [['key' => 'vp', 'label' => 'Victory Points', 'min' => 0, 'max' => 30]],
        /**
         * Scenario components a TO must set on a round before pairing/starting it. Subset of
         * 'strategy', 'deployment', 'scheme_pool' (Malifaux columns) or 'tos_operation', 'tos_deployment'
         * (stored in the round's `scenario` JSON). Empty = this ruleset has no round scenario.
         *
         * @var list<string> $scenarioFields
         */
        public readonly array $scenarioFields = ['strategy', 'deployment', 'scheme_pool'],
        /**
         * What a player declares at check-in: 'faction' (Malifaux faction), 'allegiance' (TOS Allegiance,
         * optionally with a shared Garrison) or 'none'.
         */
        public readonly string $declaration = 'faction',
        /** Whether every player must have made their declaration before the event can start. */
        public readonly bool $requiresDeclaration = true,
        /** Whether score entry records each side's leader (Malifaux master + title). */
        public readonly bool $tracksLeaders = true,
        /** Whether pairings spawn Game Tracker games (the tracker only understands Malifaux). */
        public readonly bool $supportsTracker = true,
        /**
         * Optional rule variants a TO may enable. `param` (when present) makes the variant take a number.
         *
         * @var list<array{key: string, label: string, description: string, param: array{label: string, min: int, max: int, default: int}|null}> $variants
         */
        public readonly array $variants = [],
        /**
         * Round-clock rules shown to players: minutes left when the TO calls the last turn, and the wording of
         * the last-turn / time-called rules (null = the format has no such rule).
         *
         * @var array{last_turn_minutes: int, last_turn: string|null, time_called: string|null} $timeRules
         */
        public readonly array $timeRules = ['last_turn_minutes' => 15, 'last_turn' => null, 'time_called' => null],
        /** Whether events under this ruleset can be run as team events (teams of 2–4 playing together). */
        public readonly bool $supportsTeams = true,
    ) {}

    /** @return array<string, array{key: string, label: string, description: string, param: array{label: string, min: int, max: int, default: int}|null}> */
    public function variantsByKey(): array
    {
        return array_column($this->variants, null, 'key');
    }

    public function usesScenarioField(string $field): bool
    {
        return in_array($field, $this->scenarioFields, true);
    }

    /** @return list<string> */
    public function scoreFieldKeys(): array
    {
        return array_column($this->scoreFields, 'key');
    }

    /**
     * Frontend descriptor (Create form, Manage settings).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'game_system' => $this->gameSystem,
            'scoring' => $this->scoring->toArray(),
            'tiebreakers' => $this->tiebreakers,
            'tiebreaker_toggle' => $this->tiebreakerToggle,
            'suggested_rounds' => $this->suggestedRounds,
            'default_round_time_limit' => $this->defaultRoundTimeLimit,
            'game_size' => $this->gameSize,
            'uses_season' => $this->usesSeason,
            'uses_encounter_type' => $this->usesEncounterType,
            'score_fields' => $this->scoreFields,
            'scenario_fields' => $this->scenarioFields,
            'declaration' => $this->declaration,
            'requires_declaration' => $this->requiresDeclaration,
            'tracks_leaders' => $this->tracksLeaders,
            'supports_tracker' => $this->supportsTracker,
            'variants' => $this->variants,
            'time_rules' => $this->timeRules,
            'supports_teams' => $this->supportsTeams,
        ];
    }

    /** Suggested number of rounds for a field of this size, or null if the ruleset has no guidance. */
    public function suggestedRoundsFor(int $players): ?int
    {
        foreach ($this->suggestedRounds as [$min, $max, $rounds]) {
            if ($players >= $min && ($max === null || $players <= $max)) {
                return $rounds;
            }
        }

        return null;
    }
}
