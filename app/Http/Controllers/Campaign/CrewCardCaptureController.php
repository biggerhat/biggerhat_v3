<?php

namespace App\Http\Controllers\Campaign;

use App\Http\Controllers\Controller;
use App\Models\Ability;
use App\Models\Action;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignCrewCard;
use App\Support\Campaign\CombinedCrewCardEffects;
use Inertia\Response;

/**
 * Headless-Chrome capture target for App\Services\Campaign\CrewCardImageGenerator
 * and CombinedCrewCardImageGenerator — bare card face only, no page chrome.
 * Public/unauthenticated like CustomCharacterController::capture (same trust
 * model: catalog/crew content, not a user secret), hit only by the queue worker.
 */
class CrewCardCaptureController extends Controller
{
    public function show(CampaignCrewCard $crewCard): Response
    {
        $crewCard->load([
            'actions' => fn ($q) => $q->with('triggers:id,name,suits,stone_cost,description'),
            'abilities',
        ]);

        return inertia('CardCreator/CaptureCrewCard', [
            'card' => [
                'name' => $crewCard->name,
                'body' => $crewCard->description,
                'abilities' => $crewCard->abilities->map(fn (Ability $a) => [
                    'name' => $a->name,
                    'suits' => $a->suits,
                    'defensive_ability_type' => $a->defensive_ability_type,
                    'costs_stone' => $a->costs_stone,
                    'description' => $a->description,
                ]),
                'actions' => $crewCard->actions->map(fn (Action $a) => [
                    'name' => $a->name,
                    'type' => $a->type,
                    'is_signature' => (bool) $a->pivot->is_signature_action, // @phpstan-ignore property.notFound (pivot from BelongsToMany)
                    'stone_cost' => $a->stone_cost,
                    'range' => $a->range,
                    'range_type' => $a->range_type,
                    'stat' => $a->stat,
                    'stat_suits' => $a->stat_suits,
                    'stat_modifier' => $a->stat_modifier,
                    'resisted_by' => $a->resisted_by,
                    'target_number' => $a->target_number,
                    'target_suits' => $a->target_suits,
                    'damage' => $a->damage,
                    'description' => $a->description,
                    'triggers' => $a->triggers->map(fn ($t) => [
                        'name' => $t->name,
                        'suits' => $t->suits,
                        'stone_cost' => $t->stone_cost,
                        'description' => $t->description,
                    ])->all(),
                ]),
            ],
        ]);
    }

    /**
     * The combined per-crew card (starter effect + every held Tier-4 borrow,
     * pg 15-16 / 32 / 54) — see CombinedCrewCardEffects for the shared
     * builder and how restriction qualifiers are resolved.
     */
    public function combined(CampaignCrew $crew): Response
    {
        CombinedCrewCardEffects::eagerLoad($crew);

        return inertia('CardCreator/CaptureCombinedCrewCard', [
            'crewName' => $crew->name,
            'items' => CombinedCrewCardEffects::build($crew),
        ]);
    }
}
