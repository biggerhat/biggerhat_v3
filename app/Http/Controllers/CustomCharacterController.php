<?php

namespace App\Http\Controllers;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Http\Requests\CustomCharacterRequest;
use App\Models\Campaign\CampaignCrew;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class CustomCharacterController extends Controller
{
    public function index(): Response
    {
        $characters = CustomCharacter::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        $upgrades = CustomUpgrade::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        return inertia('Tools/CardCreator/Index', [
            'characters' => $characters,
            'upgrades' => $upgrades,
        ]);
    }

    public function create(): Response
    {
        return inertia('Tools/CardCreator/Editor', [
            'character' => null,
            'enums' => $this->enumOptions(),
        ]);
    }

    public function store(CustomCharacterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        $character = CustomCharacter::create($validated);

        return response()->json([
            'success' => true,
            'redirect' => route('tools.card_creator.edit', $character->id),
        ]);
    }

    public function edit(CustomCharacter $customCharacter): Response
    {
        $this->authorize('update', $customCharacter);

        $campaignBackUrl = null;
        if ($customCharacter->is_campaign_leader && $customCharacter->campaign_crew_id) {
            $crew = CampaignCrew::find($customCharacter->campaign_crew_id);
            if ($crew) {
                $campaignBackUrl = route('campaigns.crews.arsenal.show', [$crew->campaign_id, $crew->share_code]);
            }
        }

        return inertia('Tools/CardCreator/Editor', [
            'character' => $customCharacter,
            'enums' => $this->enumOptions(),
            'campaign_back_url' => $campaignBackUrl,
        ]);
    }

    public function update(CustomCharacterRequest $request, CustomCharacter $customCharacter): JsonResponse
    {
        $validated = $request->validated();

        // A campaign leader can be edited here for action/ability detail, but the
        // generic editor must not break its campaign invariants — it must stay a
        // cost-0, stone-generating Master (pg 18). Campaign-only fields (tag,
        // archetype, is_campaign_leader, current, campaign_*) aren't in the
        // validated set, so update() preserves them untouched.
        if ($customCharacter->is_campaign_leader) {
            $validated['station'] = 'master';
            $validated['generates_stone'] = true;
            $validated['is_unhirable'] = false;
            $validated['cost'] = null;
        }

        $customCharacter->update($validated);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(CustomCharacter $customCharacter): JsonResponse
    {
        $this->authorize('delete', $customCharacter);

        $customCharacter->delete();

        return response()->json(['success' => true]);
    }

    public function share(string $shareCode): Response
    {
        $character = CustomCharacter::where('share_code', $shareCode)
            ->with('user:id,name')
            ->firstOrFail();

        return inertia('Tools/CardCreator/View', [
            'character' => $character,
            'creator_name' => $character->user->name,
        ]);
    }

    /**
     * Bare front/back card faces, no page chrome — the headless-Chrome
     * capture target for App\Services\Campaign\LeaderCardImageGenerator.
     * Props are shaped here (camelCase, enums resolved to their value)
     * instead of client-side, matching what CardFrontFace/CardBackFace
     * expect directly, so the capture page needs no adapter logic.
     */
    public function capture(string $shareCode): Response
    {
        $character = CustomCharacter::where('share_code', $shareCode)->firstOrFail();

        return inertia('CardCreator/Capture', [
            'card' => [
                'name' => $character->name,
                'title' => $character->title,
                'faction' => $character->faction?->value,
                'secondFaction' => $character->second_faction?->value,
                'station' => $character->station?->value,
                'cost' => $character->cost,
                'health' => $character->health,
                'defense' => $character->defense,
                'defenseSuit' => $character->defense_suit?->value,
                'willpower' => $character->willpower,
                'willpowerSuit' => $character->willpower_suit?->value,
                'speed' => $character->speed,
                'size' => $character->size,
                'base' => (string) ($character->base->value ?? ''),
                'keywords' => $character->keywords ?? [],
                'characteristics' => $character->characteristics ?? [],
                // Never populated — Custom Card Creator character art is
                // client-blob-only, not persisted server-side.
                'characterImage' => null,
                'actions' => $character->actions ?? [],
                'abilities' => $character->abilities ?? [],
                'linkedCrewUpgrades' => $character->linked_crew_upgrades ?? [],
                'linkedTotems' => $character->linked_totems ?? [],
            ],
        ]);
    }

    private function enumOptions(): array
    {
        return [
            'factions' => FactionEnum::toSelectOptions(),
            'stations' => CharacterStationEnum::toSelectOptions(),
            'bases' => BaseSizeEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
            'action_types' => ActionTypeEnum::toSelectOptions(),
            'range_types' => ActionRangeTypeEnum::toSelectOptions(),
            'defensive_ability_types' => DefensiveAbilityTypeEnum::toSelectOptions(),
        ];
    }
}
