<?php

namespace App\Tournaments;

use App\Models\CrewBuild;
use App\Models\Tournament;
use App\Models\TournamentPlayer;

/**
 * What an organizer sees about a player's submitted Crew Builder list, with the problems worth a second look.
 * These are flags, not rules: the Crew Builder is the authority on what a legal crew is.
 */
class CrewListCheck
{
    /**
     * @return array{id: int, name: string, share_code: string, master: string|null, faction: string, encounter_size: int, problems: list<string>}|null
     */
    public static function summarize(Tournament $tournament, TournamentPlayer $player): ?array
    {
        /** @var CrewBuild|null $build */
        $build = $player->crewBuild;
        if ($build === null) {
            return null;
        }

        $problems = [];
        $declared = $player->getRawOriginal('faction');
        $factionLabel = $build->faction->label();
        if ($declared !== null && $declared !== $build->faction->value) {
            $problems[] = "The crew is {$factionLabel}, but the player declared a different faction.";
        }
        if ((int) $build->encounter_size !== (int) $tournament->encounter_size) {
            $problems[] = "Built for {$build->encounter_size}ss; the event is {$tournament->encounter_size}ss.";
        }
        if (! $build->master_id) {
            $problems[] = 'No master chosen.';
        }
        if (empty($build->crew_data)) {
            $problems[] = 'No models in the crew.';
        }

        return [
            'id' => $build->id,
            'name' => $build->name,
            'share_code' => $build->share_code,
            'master' => data_get($build, 'master.display_name'),
            'faction' => $build->faction->value,
            'encounter_size' => (int) $build->encounter_size,
            'problems' => $problems,
        ];
    }
}
