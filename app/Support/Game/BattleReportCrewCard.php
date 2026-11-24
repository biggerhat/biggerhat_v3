<?php

namespace App\Support\Game;

use App\Models\Character;
use App\Models\CrewBuild;

/**
 * The crew list shown on a battle report: the faction, master and models only. The crew's own name, description and notes are the
 * writer's free text, which a moderator never read as part of the report, so they are left out.
 */
class BattleReportCrewCard
{
    /** @return array<string, mixed> */
    public static function for(CrewBuild $crew): array
    {
        $ids = array_values(array_filter((array) $crew->crew_data, 'is_int'));
        $characters = Character::whereIn('id', array_unique($ids))->get(['id', 'display_name', 'cost', 'station'])->keyBy('id');

        $models = collect($ids)->filter(fn (int $id) => $characters->has($id))->countBy()->map(function (int $count, int $id) use ($characters) {
            $c = $characters[$id];

            return ['name' => $c->display_name, 'count' => $count, 'cost' => $c->cost];
        })->values()->sortBy('name')->values()->all();

        return [
            'faction' => $crew->faction->value,
            'faction_label' => $crew->faction->label(),
            'master' => $crew->master_id ? Character::find($crew->master_id, ['id', 'display_name'])?->display_name : null,
            'encounter_size' => $crew->encounter_size,
            'models' => $models,
            'cost' => (int) collect($models)->sum(fn (array $m) => ($m['cost'] ?? 0) * $m['count']),
            // The crew's own page, only when it is public.
            'url' => $crew->is_public ? route('tools.crew_builder.share', $crew->share_code) : null,
        ];
    }
}
