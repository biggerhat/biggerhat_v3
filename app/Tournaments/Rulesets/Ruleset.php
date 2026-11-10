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
        /** Whether every player needs a Malifaux-style faction before the event can start. */
        public readonly bool $requiresFaction = true,
        /** Whether pairings spawn Game Tracker games (the tracker only understands Malifaux). */
        public readonly bool $supportsTracker = true,
    ) {}

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
            'requires_faction' => $this->requiresFaction,
            'supports_tracker' => $this->supportsTracker,
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
