<?php

namespace App\Support\Stats;

/**
 * One finished game from one player's side, whether it came from the Game Tracker, was logged by hand or was a tournament round. Every
 * statistic is worked out from these, so a number cannot differ between sources. Anything a source does not record is null (or an
 * empty list): the calculators skip it and the analytics say how many games they could use.
 */
final class PlayedGame
{
    public const TRACKED = 'tracked';

    public const LOGGED = 'logged';

    public const TOURNAMENT = 'tournament';

    /**
     * @param  'win'|'loss'|'tie'  $result
     * @param  list<array{turn: int, strategy: int, scheme: int, scheme_id: int|null, scheme_name: string|null, action: string|null, opp_strategy: int, opp_scheme: int}>  $turns
     * @param  list<array{name: string, killed: bool, cost: int|null}>  $models
     */
    public function __construct(
        public readonly string $source,
        public readonly string $key,
        public readonly string $url,
        public readonly string $date,
        public readonly string $result,
        public readonly bool $isSolo,
        public readonly ?string $faction,
        public readonly ?string $master,
        public readonly ?string $role,
        public readonly int $vp,
        public readonly ?string $oppName,
        public readonly ?string $oppSlug,
        public readonly ?string $oppFaction,
        public readonly ?string $oppMaster,
        public readonly int $oppVp,
        public readonly ?string $strategy = null,
        public readonly ?string $deployment = null,
        public readonly ?int $encounterSize = null,
        public readonly ?string $season = null,
        public readonly ?string $playMode = null,
        public readonly ?string $venue = null,
        public readonly ?string $format = null,
        public readonly array $turns = [],
        public readonly array $models = [],
        public readonly ?int $strategyVp = null,
        public readonly ?int $schemeVp = null,
    ) {}

    /** @return array<string, mixed> */
    public function toRow(): array
    {
        return [
            'source' => $this->source,
            'url' => $this->url,
            'date' => $this->date,
            'result' => $this->result,
            'is_solo' => $this->isSolo,
            'faction' => $this->faction,
            'master' => $this->master,
            'role' => $this->role,
            'vp' => $this->vp,
            'opponent' => $this->oppName,
            'opponent_slug' => $this->oppSlug,
            'opponent_faction' => $this->oppFaction,
            'opponent_master' => $this->oppMaster,
            'opponent_vp' => $this->oppVp,
            'strategy' => $this->strategy,
            'deployment' => $this->deployment,
            'encounter_size' => $this->encounterSize,
            'season' => $this->season,
            'play_mode' => $this->playMode,
            'venue' => $this->venue,
        ];
    }
}
