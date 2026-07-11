<?php

namespace App\Http\Controllers;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\BaseSizeEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Models\Campaign\CampaignCrew;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCharacter($request);
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

    public function update(Request $request, CustomCharacter $customCharacter): JsonResponse
    {
        $this->authorize('update', $customCharacter);

        $validated = $this->validateCharacter($request);

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

    private function validateCharacter(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'faction' => ['required', 'string'],
            'second_faction' => ['nullable', 'string'],
            'station' => ['nullable', 'string'],
            'cost' => ['nullable', 'integer', 'min:0', 'max:99'],
            'health' => ['required', 'integer', 'min:1', 'max:99'],
            'size' => ['nullable', 'integer', 'min:0', 'max:10'],
            'base' => ['required', 'string'],
            'defense' => ['required', 'integer', 'min:0', 'max:20'],
            'defense_suit' => ['nullable', 'string'],
            'willpower' => ['required', 'integer', 'min:0', 'max:20'],
            'willpower_suit' => ['nullable', 'string'],
            'speed' => ['required', 'integer', 'min:0', 'max:20'],
            'count' => ['nullable', 'integer', 'min:1', 'max:10'],
            'summon_target_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'generates_stone' => ['boolean'],
            'is_unhirable' => ['boolean'],
            'actions' => ['nullable', 'array'],
            'actions.*.name' => ['required', 'string', 'max:255'],
            'actions.*.type' => ['required', 'string'],
            'actions.*.is_signature' => ['boolean'],
            'actions.*.stone_cost' => ['nullable'],
            'actions.*.range' => ['nullable'],
            'actions.*.range_type' => ['nullable', 'string'],
            'actions.*.stat' => ['nullable'],
            'actions.*.stat_suits' => ['nullable', 'string'],
            'actions.*.stat_modifier' => ['nullable', 'string'],
            'actions.*.resisted_by' => ['nullable', 'string'],
            'actions.*.target_number' => ['nullable'],
            'actions.*.target_suits' => ['nullable', 'string'],
            'actions.*.damage' => ['nullable', 'string'],
            'actions.*.description' => ['nullable', 'string'],
            'actions.*.source_id' => ['nullable', 'integer'],
            'actions.*.triggers' => ['nullable', 'array'],
            'actions.*.triggers.*.name' => ['required', 'string', 'max:255'],
            'actions.*.triggers.*.suits' => ['nullable', 'string'],
            'actions.*.triggers.*.stone_cost' => ['nullable'],
            'actions.*.triggers.*.description' => ['nullable', 'string'],
            'actions.*.triggers.*.source_id' => ['nullable', 'integer'],
            'abilities' => ['nullable', 'array'],
            'abilities.*.name' => ['required', 'string', 'max:255'],
            'abilities.*.suits' => ['nullable', 'string'],
            'abilities.*.defensive_ability_type' => ['nullable', 'string'],
            'abilities.*.costs_stone' => ['boolean'],
            'abilities.*.description' => ['nullable', 'string'],
            'abilities.*.source_id' => ['nullable', 'integer'],
            'keywords' => ['nullable', 'array'],
            'keywords.*.id' => ['nullable', 'integer'],
            'keywords.*.name' => ['required', 'string', 'max:255'],
            'characteristics' => ['nullable', 'array'],
            'characteristics.*' => ['string', 'max:255'],
            'linked_crew_upgrades' => ['nullable', 'array'],
            'linked_crew_upgrades.*.source_type' => ['required', 'string', 'in:official,custom'],
            'linked_crew_upgrades.*.id' => ['required', 'integer'],
            'linked_crew_upgrades.*.name' => ['required', 'string', 'max:255'],
            'linked_totems' => ['nullable', 'array'],
            'linked_totems.*.source_type' => ['required', 'string', 'in:official,custom'],
            'linked_totems.*.id' => ['required', 'integer'],
            'linked_totems.*.name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
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
