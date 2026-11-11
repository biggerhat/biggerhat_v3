<?php

namespace App\Tournaments\Rulesets;

use App\Tournaments\ScoringConfig;
use InvalidArgumentException;

/**
 * Resolves ruleset keys to definitions built from config('tournaments.rulesets').
 */
final class RulesetRegistry
{
    /** @var array<string, Ruleset> */
    private array $resolved = [];

    /**
     * @param  array<string, array<string, mixed>>  $definitions
     */
    public function __construct(private readonly array $definitions, private readonly string $defaultKey) {}

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function default(): Ruleset
    {
        return $this->get($this->defaultKey);
    }

    /** Resolve a key, falling back to the default ruleset for null/unknown keys (e.g. a ruleset removed from config). */
    public function resolve(?string $key): Ruleset
    {
        return $key !== null && $this->has($key) ? $this->get($key) : $this->default();
    }

    public function get(string $key): Ruleset
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown tournament ruleset [{$key}].");
        }

        return $this->resolved[$key] ??= $this->build($key, $this->definitions[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->definitions);
    }

    /** @return array<string, Ruleset> */
    public function all(): array
    {
        return array_combine($this->keys(), array_map(fn (string $k) => $this->get($k), $this->keys()));
    }

    /** @param  array<string, mixed>  $def */
    private function build(string $key, array $def): Ruleset
    {
        return new Ruleset(
            key: $key,
            label: (string) $def['label'],
            gameSystem: (string) $def['game_system'],
            scoring: ScoringConfig::fromArray((array) ($def['scoring'] ?? [])),
            tiebreakers: array_values((array) ($def['tiebreakers'] ?? ['diff', 'vp'])),
            tiebreakerToggle: (bool) ($def['tiebreaker_toggle'] ?? false),
            suggestedRounds: array_values((array) ($def['suggested_rounds'] ?? [])),
            defaultRoundTimeLimit: isset($def['default_round_time_limit']) ? (int) $def['default_round_time_limit'] : null,
            gameSize: array_merge(
                ['label' => 'Encounter Size (SS)', 'unit' => 'ss', 'default' => 50, 'min' => 20, 'max' => 100, 'step' => 5],
                (array) ($def['game_size'] ?? []),
            ),
            usesSeason: (bool) ($def['uses_season'] ?? true),
            usesEncounterType: (bool) ($def['uses_encounter_type'] ?? true),
            scenarioFields: array_values((array) ($def['scenario_fields'] ?? ['strategy', 'deployment', 'scheme_pool'])),
            declaration: (string) ($def['declaration'] ?? 'faction'),
            requiresDeclaration: (bool) ($def['requires_declaration'] ?? true),
            tracksLeaders: (bool) ($def['tracks_leaders'] ?? true),
            supportsTracker: (bool) ($def['supports_tracker'] ?? true),
            scoreFields: array_values(array_map(
                fn (array $f) => ['key' => (string) $f['key'], 'label' => (string) $f['label'], 'min' => (int) ($f['min'] ?? 0), 'max' => (int) $f['max']],
                (array) ($def['score_fields'] ?? [['key' => 'vp', 'label' => 'Victory Points', 'min' => 0, 'max' => 30]]),
            )),
        );
    }
}
