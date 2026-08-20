<?php

namespace App\Http\Controllers;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\BaseSizeEnum;
use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\Campaign\LeaderTagEnum;
use App\Enums\CharacterStationEnum;
use App\Enums\DefensiveAbilityTypeEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Events\CampaignCrewUpdated;
use App\Http\Controllers\Campaign\Concerns\BroadcastsCampaignEvents;
use App\Http\Requests\CustomCharacterRequest;
use App\Models\Campaign\CampaignCrew;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Response;

class CustomCharacterController extends Controller
{
    use BroadcastsCampaignEvents;

    public function index(): Response
    {
        $characters = CustomCharacter::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        $upgrades = CustomUpgrade::where('user_id', Auth::id())
            ->orderBy('updated_at', 'desc')
            ->get();

        // Same "still live" definition destroy() enforces server-side — computed
        // here in one batched query (not per-row) so the frontend can disable
        // the delete button only when a campaign is genuinely still active,
        // rather than unconditionally for any campaign-linked record. Without
        // this the delete button stayed disabled forever even after destroy()
        // itself was fixed to allow deletion once a campaign ends/is deleted —
        // the UI never let anyone reach that backend logic.
        $crewIds = $characters->pluck('campaign_crew_id')
            ->merge($upgrades->pluck('campaign_crew_id'))
            ->filter()
            ->unique();

        $liveCrewIds = CampaignCrew::whereIn('id', $crewIds)
            ->whereHas('campaign', fn ($q) => $q->where('status', '!=', CampaignStatusEnum::Ended->value))
            ->pluck('id');

        // Plain foreach, not ->each() — Collection::each() treats a callback
        // returning false as "stop iterating", and an arrow function whose
        // body is an assignment implicitly returns the assigned value. Any
        // character/upgrade whose campaign isn't live assigns `false` here,
        // silently truncating the loop for every item after it.
        foreach ($characters as $c) {
            $c->campaign_still_live = $c->campaign_crew_id !== null && $liveCrewIds->contains($c->campaign_crew_id);
        }
        foreach ($upgrades as $u) {
            $u->campaign_still_live = $u->campaign_crew_id !== null && $liveCrewIds->contains($u->campaign_crew_id);
        }

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
        if (($customCharacter->is_campaign_leader || $customCharacter->is_campaign_totem) && $customCharacter->campaign_crew_id) {
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

        // A campaign Totem has the same "don't let the generic editor break its
        // campaign invariants" problem as the Leader above — it must stay a
        // cost-0, hireable-for-free, station-less model (pg 32).
        if ($customCharacter->is_campaign_totem) {
            $validated['station'] = null;
            $validated['is_unhirable'] = true;
            $validated['cost'] = null;
        }

        $customCharacter->update($validated);

        // Leader/Totem saves through this generic editor never broadcast to the
        // campaign (unlike every other Leader/Totem-mutating path — Starting
        // Arsenal, Advancement, Crew Lifecycle, etc.), so other viewers of the
        // Arsenal Sheet — and the editor's own return trip back to it — saw
        // stale data until a manual reload.
        if (($customCharacter->is_campaign_leader || $customCharacter->is_campaign_totem) && $customCharacter->campaign_crew_id) {
            $crew = CampaignCrew::find($customCharacter->campaign_crew_id);
            if ($crew) {
                $this->broadcastToCampaign($crew->campaign, new CampaignCrewUpdated($crew));
            }
        }

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(CustomCharacter $customCharacter): JsonResponse
    {
        $this->authorize('delete', $customCharacter);

        // A Campaign Leader/Totem is referenced by that crew's advancement
        // log, injuries, and every game it's been hired into — deleting it
        // here (soft-delete, but Eloquent's global scope hides it from every
        // normal query just the same) silently breaks the crew, with no
        // affordance in this generic editor to reassign a replacement.
        // Checked regardless of `current`: a superseded (replaced) Leader
        // row is still the historical record for past logged games.
        // Once the owning campaign has ended (or been deleted — the FK cascade
        // nulls campaign_crew_id but never these flags), there's no longer a
        // live crew for it to break, so deletion is allowed again.
        if ($customCharacter->is_campaign_leader || $customCharacter->is_campaign_totem) {
            $stillLive = $customCharacter->campaign_crew_id !== null
                && CampaignCrew::whereKey($customCharacter->campaign_crew_id)
                    ->whereHas('campaign', fn ($q) => $q->where('status', '!=', CampaignStatusEnum::Ended->value))
                    ->exists();

            if ($stillLive) {
                return response()->json([
                    'success' => false,
                    'message' => "{$customCharacter->display_name} is tied to a Campaign crew and can't be deleted here — it's still referenced by that crew's advancement log and game history.",
                ], 422);
            }
        }

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

        // The Bruiser/Strategist tag (pg 18) and the "Unique" characteristic
        // (every built Leader is a one-of-a-kind named model) are shown on
        // the rendered card alongside the player's own up-to-two picks. This
        // is display-only — appended here rather than persisted onto the
        // `characteristics` column — so it doesn't round-trip into the
        // Leader Builder edit form and get counted against that two-pick cap
        // or duplicated on repeat saves.
        $characteristics = $character->characteristics ?? [];
        if ($character->is_campaign_leader) {
            if (! in_array('Unique', $characteristics, true)) {
                $characteristics[] = 'Unique';
            }
            if ($tag = LeaderTagEnum::tryFrom((string) $character->tag)) {
                $characteristics[] = $tag->label();
            }
        }

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
                'characteristics' => $characteristics,
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
