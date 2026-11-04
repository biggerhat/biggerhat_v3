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
use App\Jobs\Campaign\GenerateLeaderCardImage;
use App\Models\Campaign\CampaignCrew;
use App\Models\CardArt;
use App\Models\CustomCharacter;
use App\Models\CustomUpgrade;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Response;

class CustomCharacterController extends Controller
{
    /** @var array<int, string> */
    private const PUBLIC_SHARE_FIELDS = [
        'id', 'name', 'title', 'display_name', 'faction', 'second_faction', 'station', 'cost', 'health', 'defense',
        'defense_suit', 'willpower', 'willpower_suit', 'speed', 'size', 'base', 'keywords', 'characteristics', 'actions',
        'abilities', 'linked_crew_upgrades', 'linked_totems', 'card_art_x', 'card_art_y', 'card_art_zoom', 'card_art_dx',
        'card_art_dy', 'front_image', 'back_image', 'combination_image', 'card_image_generated_at',
    ];

    use BroadcastsCampaignEvents;

    public function index(): Response
    {
        // Only what the tiles render: the full rows carry every action, ability and note, which for a user with many
        // cards is most of the payload. (card_art_id feeds the cardArt relation; faction_color is an appended accessor
        // that reads faction.)
        $characters = CustomCharacter::where('user_id', Auth::id())
            ->select([
                'id', 'name', 'title', 'display_name', 'slug', 'faction', 'second_faction', 'station', 'cost', 'health', 'share_code',
                'updated_at', 'front_image', 'thumb_image', 'combination_image', 'card_image_generated_at', 'card_art_id', 'is_campaign_leader',
                'is_campaign_totem', 'campaign_crew_id',
            ])
            ->with('cardArt:id,image_path')
            ->orderBy('updated_at', 'desc')
            ->get();

        // Tile thumbnail fallback while a card's saved image is still rendering.
        foreach ($characters as $c) {
            $c->setAttribute('card_art_path', $c->cardArt?->image_path);
            $c->setAttribute('card_image_pending', $c->cardImagePending());
            $c->unsetRelation('cardArt');
        }

        $upgrades = CustomUpgrade::where('user_id', Auth::id())
            ->select([
                'id', 'name', 'display_name', 'slug', 'domain', 'type', 'faction', 'master_name', 'share_code', 'updated_at',
                'front_image', 'thumb_image', 'combination_image', 'card_image_generated_at', 'is_campaign_crew_card', 'campaign_crew_id',
            ])
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
            $u->setAttribute('card_image_pending', $u->cardImagePending());
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
            'card_arts' => CardArt::pickerOptions(),
        ]);
    }

    public function store(CustomCharacterRequest $request): JsonResponse
    {
        $validated = CardArt::normalizeFraming($request->validated());
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
            'card_arts' => CardArt::pickerOptions($customCharacter->card_art_id),
            'campaign_back_url' => $campaignBackUrl,
            'card_image_pending' => $customCharacter->cardImagePending(),
        ]);
    }

    public function update(CustomCharacterRequest $request, CustomCharacter $customCharacter): JsonResponse
    {
        $validated = CardArt::normalizeFraming($request->validated());

        // A campaign leader can be edited here for action/ability/tag detail,
        // but the generic editor must not break its campaign invariants — it
        // must stay a cost-0, stone-generating Master (pg 18). Other
        // campaign-only fields (archetype, is_campaign_leader, current,
        // campaign_*) aren't in the validated set, so update() preserves
        // them untouched. tag has no rules-derived effect (pg 18's +1 XP
        // condition is self-reported at Aftermath, not enforced here), so
        // there's no invariant to protect by locking it.
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

    /**
     * Copies one of the owner's cards into a new, plain custom character (no campaign linkage, fresh share link and
     * saved image) and sends them to its editor. Works from an explicit field list so campaign bookkeeping, XP
     * and injuries never come along.
     */
    public function duplicate(CustomCharacter $customCharacter): JsonResponse
    {
        $this->authorize('update', $customCharacter);

        if (CustomCharacter::where('user_id', Auth::id())->count() >= CustomCharacterRequest::MAX_PER_USER) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the limit of '.CustomCharacterRequest::MAX_PER_USER.' custom characters. Delete one you no longer need first.',
            ], 422);
        }

        $copy = CustomCharacter::create(array_merge(
            $customCharacter->only([
                'title', 'faction', 'second_faction', 'station', 'cost', 'health', 'size', 'base', 'defense', 'defense_suit',
                'willpower', 'willpower_suit', 'speed', 'count', 'summon_target_number', 'generates_stone', 'is_unhirable',
                'card_art_id', 'card_art_x', 'card_art_y', 'card_art_zoom', 'card_art_dx', 'card_art_dy', 'actions',
                'abilities', 'keywords', 'characteristics', 'linked_crew_upgrades', 'linked_totems', 'notes',
            ]),
            [
                'user_id' => Auth::id(),
                'name' => Str::limit($customCharacter->name, 52, '').' (Copy)',
            ],
        ));

        return response()->json(['success' => true, 'redirect' => route('tools.card_creator.edit', $copy->id)]);
    }

    /**
     * Owner-triggered re-render of the saved card image (a failed or stale render). Same job the observer queues,
     * but immediate; the pending flag makes My Cards show "Rendering…" until CardImageGenerated arrives.
     */
    public function rerender(CustomCharacter $customCharacter): JsonResponse
    {
        $this->authorize('update', $customCharacter);

        if (! $customCharacter->cardImagePending()) {
            Cache::put(CustomCharacter::renderingKey($customCharacter->id), true, 150);
            GenerateLeaderCardImage::dispatch($customCharacter->id)->afterCommit();
        }

        return response()->json(['success' => true]);
    }

    public function share(string $shareCode): Response
    {
        $character = CustomCharacter::where('share_code', $shareCode)
            ->with('user:id,name')
            ->firstOrFail();

        // An explicit allow-list: the model also carries the owner's private notes, user id and campaign bookkeeping,
        // and this page is public.
        return inertia('Tools/CardCreator/View', [
            'character' => $character->only(self::PUBLIC_SHARE_FIELDS),
            'card_art_path' => $character->cardArt?->image_path,
            'card_image_pending' => $character->cardImagePending(),
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

        $characteristics = $character->displayCharacteristics();

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
                // Library art the player picked (CardArt); uploads are preview-only and never reach here.
                'characterImage' => $character->cardArt?->image_path,
                'characterImageFocus' => ['x' => $character->card_art_x, 'y' => $character->card_art_y, 'zoom' => $character->card_art_zoom, 'dx' => $character->card_art_dx, 'dy' => $character->card_art_dy],
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
            // Campaign Leader's Bruiser/Strategist tag (pg 18) — editable here
            // post-creation (QA: doesn't drive any automatic rule yet, so
            // there's no reason to lock it once the Leader is built).
            'tags' => LeaderTagEnum::toSelectOptions(),
        ];
    }
}
