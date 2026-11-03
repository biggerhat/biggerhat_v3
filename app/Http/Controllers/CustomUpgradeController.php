<?php

namespace App\Http\Controllers;

use App\Enums\ActionRangeTypeEnum;
use App\Enums\ActionTypeEnum;
use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\FactionEnum;
use App\Enums\SuitEnum;
use App\Enums\UpgradeDomainTypeEnum;
use App\Enums\UpgradeLimitationEnum;
use App\Enums\UpgradeTypeEnum;
use App\Http\Requests\CustomUpgradeRequest;
use App\Jobs\Campaign\GenerateCustomUpgradeCardImage;
use App\Models\Campaign\CampaignCrew;
use App\Models\CustomUpgrade;
use App\Support\Campaign\CombinedCrewCardEffects;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Response;

class CustomUpgradeController extends Controller
{
    public function create(Request $request): Response
    {
        $domain = $request->query('domain', 'character');
        if (! in_array($domain, ['character', 'crew'])) {
            $domain = 'character';
        }

        return inertia('Tools/CardCreator/UpgradeEditor', [
            'upgrade' => null,
            'domain' => $domain,
            'enums' => $this->enumOptions(),
            'campaign_back_url' => null,
            'crew_computed_tokens_markers' => null,
        ]);
    }

    public function store(CustomUpgradeRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = Auth::id();

        $upgrade = CustomUpgrade::create($validated);

        return response()->json([
            'success' => true,
            'redirect' => route('tools.card_creator.upgrades.edit', $upgrade->id),
        ]);
    }

    public function edit(CustomUpgrade $customUpgrade): Response
    {
        $this->authorize('update', $customUpgrade);

        $campaignBackUrl = null;
        $crewComputedTokensMarkers = null;
        if ($customUpgrade->is_campaign_crew_card && $customUpgrade->campaign_crew_id) {
            $crew = CampaignCrew::find($customUpgrade->campaign_crew_id);
            if ($crew) {
                $campaignBackUrl = route('campaigns.crews.arsenal.show', [$crew->campaign_id, $crew->share_code]);
                // The real generated card's back face reads this, not this
                // upgrade's own back_tokens/back_markers columns — shown
                // read-only in the editor so it doesn't look like a dead end.
                $crewComputedTokensMarkers = CombinedCrewCardEffects::arsenalTokensAndMarkers($crew);
            }
        }

        return inertia('Tools/CardCreator/UpgradeEditor', [
            'upgrade' => $customUpgrade,
            'domain' => $customUpgrade->domain instanceof UpgradeDomainTypeEnum ? $customUpgrade->domain->value : $customUpgrade->domain,
            'enums' => $this->enumOptions(),
            'campaign_back_url' => $campaignBackUrl,
            'crew_computed_tokens_markers' => $crewComputedTokensMarkers,
            'card_image_pending' => $customUpgrade->cardImagePending(),
        ]);
    }

    public function update(CustomUpgradeRequest $request, CustomUpgrade $customUpgrade): JsonResponse
    {
        $validated = $request->validated();

        $customUpgrade->update($validated);

        return response()->json([
            'success' => true,
        ]);
    }

    public function destroy(CustomUpgrade $customUpgrade): JsonResponse
    {
        $this->authorize('delete', $customUpgrade);

        // Mirrors the Campaign Leader/Totem guard on CustomCharacterController —
        // this row is the snapshot StartingArsenalController saves into the
        // owner's Card Creator library when they name their crew card. Once
        // the owning campaign has ended (or been deleted — the FK cascade
        // nulls campaign_crew_id but never this flag), deletion is allowed again.
        if ($customUpgrade->is_campaign_crew_card) {
            $stillLive = $customUpgrade->campaign_crew_id !== null
                && CampaignCrew::whereKey($customUpgrade->campaign_crew_id)
                    ->whereHas('campaign', fn ($q) => $q->where('status', '!=', CampaignStatusEnum::Ended->value))
                    ->exists();

            if ($stillLive) {
                return response()->json([
                    'success' => false,
                    'message' => "{$customUpgrade->display_name} is tied to a Campaign crew and can't be deleted here.",
                ], 422);
            }
        }

        $customUpgrade->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Bare front/back faces, no page chrome — the headless-Chrome capture target for CustomUpgradeCardImageGenerator.
     * Public like `share` (same unguessable share_code trust model), hit only by the queue worker.
     */
    public function capture(string $shareCode): Response
    {
        $upgrade = CustomUpgrade::where('share_code', $shareCode)->firstOrFail();

        return inertia('CardCreator/UpgradeCapture', [
            'upgrade' => $upgrade->only([
                'name', 'domain', 'faction', 'type', 'limitations', 'master_name', 'keyword_name', 'content_blocks', 'back_tokens', 'back_markers',
            ]),
        ]);
    }

    /** Owner-triggered re-render of the saved card image; ignored while one is already pending. */
    public function rerender(CustomUpgrade $customUpgrade): JsonResponse
    {
        $this->authorize('update', $customUpgrade);

        if (! $customUpgrade->cardImagePending()) {
            Cache::put(CustomUpgrade::renderingKey($customUpgrade->id), true, 150);
            GenerateCustomUpgradeCardImage::dispatch($customUpgrade->id)->afterCommit();
        }

        return response()->json(['success' => true]);
    }

    /** Copies one of the owner's upgrades / crew cards into a new plain custom card (no campaign linkage). */
    public function duplicate(CustomUpgrade $customUpgrade): JsonResponse
    {
        $this->authorize('update', $customUpgrade);

        if (CustomUpgrade::where('user_id', Auth::id())->count() >= CustomUpgradeRequest::MAX_PER_USER) {
            return response()->json([
                'success' => false,
                'message' => 'You have reached the limit of '.CustomUpgradeRequest::MAX_PER_USER.' custom upgrades and crew cards. Delete one you no longer need first.',
            ], 422);
        }

        $copy = CustomUpgrade::create(array_merge(
            $customUpgrade->only([
                'domain', 'faction', 'type', 'limitations', 'plentiful', 'master_name', 'keyword_name', 'content_blocks', 'back_tokens', 'back_markers', 'notes',
            ]),
            ['user_id' => Auth::id(), 'name' => Str::limit($customUpgrade->name, 245, '').' (Copy)'],
        ));

        return response()->json(['success' => true, 'redirect' => route('tools.card_creator.upgrades.edit', $copy->id)]);
    }

    public function share(string $shareCode): Response
    {
        $upgrade = CustomUpgrade::where('share_code', $shareCode)
            ->with('user:id,name')
            ->firstOrFail();

        /** @var \App\Models\User $user */
        $user = $upgrade->user;

        return inertia('Tools/CardCreator/UpgradeView', [
            // Allow-list: the model also carries the owner's private notes and user id, and this page is public.
            'upgrade' => $upgrade->only([
                'id', 'name', 'display_name', 'domain', 'type', 'faction', 'limitations', 'plentiful', 'master_name',
                'keyword_name', 'content_blocks', 'back_tokens', 'back_markers', 'front_image', 'back_image', 'combination_image',
                'card_image_generated_at',
            ]),
            'card_image_pending' => $upgrade->cardImagePending(),
            'creator_name' => $user->name,
        ]);
    }

    private function enumOptions(): array
    {
        return [
            'factions' => FactionEnum::toSelectOptions(),
            'upgrade_types' => UpgradeTypeEnum::toSelectOptions(),
            'limitations' => UpgradeLimitationEnum::toSelectOptions(),
            'suits' => SuitEnum::toSelectOptions(),
            'action_types' => ActionTypeEnum::toSelectOptions(),
            'range_types' => ActionRangeTypeEnum::toSelectOptions(),
        ];
    }
}
