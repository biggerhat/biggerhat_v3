<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\CampaignPlayerRoleEnum;
use App\Enums\Campaign\CampaignStatusEnum;
use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Campaign\UpdateCampaignRequest;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignPlayer;
use App\Models\Game;
use App\Traits\Campaign\AddsCampaignMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CampaignController extends Controller
{
    use AddsCampaignMember;

    public function index(Request $request)
    {
        $user = $request->user();

        $campaigns = Campaign::query()
            ->whereIn('id', CampaignPlayer::query()->where('user_id', $user->id)->select('campaign_id'))
            ->with('organizer:id,name')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'length_weeks', 'current_week', 'organizer_user_id', 'status', 'is_solo', 'started_at', 'ended_at']);

        return inertia('Campaigns/Index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function create(Request $request)
    {
        return inertia('Campaigns/Create');
    }

    public function store(StoreCampaignRequest $request)
    {
        $data = $request->validated();

        $campaign = DB::transaction(function () use ($data, $request) {
            $campaign = Campaign::create([
                ...$data,
                'organizer_user_id' => $request->user()->id,
                'status' => CampaignStatusEnum::Planning,
                'current_week' => 1,
            ]);

            // Organizer is auto-added as a player with the Organizer role so
            // the membership check works uniformly across organizer + invited
            // players (no special-case for the creator).
            CampaignPlayer::create([
                'campaign_id' => $campaign->id,
                'user_id' => $request->user()->id,
                'role' => CampaignPlayerRoleEnum::Organizer,
            ]);

            // Solo campaigns don't go through the invite/accept flow, so the
            // organizer's crew stub has to be minted here — every downstream
            // page (Leader Builder, Arsenal, Log Game) expects it to exist.
            if ($campaign->is_solo) {
                CampaignCrew::firstOrCreate(
                    ['campaign_id' => $campaign->id, 'user_id' => $request->user()->id],
                    ['name' => $request->user()->name."'s Crew"],
                );
            }

            return $campaign;
        });

        return redirect()->route('campaigns.show', $campaign)
            ->withMessage("{$campaign->name} created.");
    }

    public function show(Request $request, Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        // Backfill the organizer's crew on solo campaigns that predate the
        // auto-crew patch in store(). Idempotent firstOrCreate, scoped to the
        // visiting user — runs once per stale campaign and never duplicates.
        if ($campaign->is_solo && $request->user()) {
            CampaignCrew::firstOrCreate(
                ['campaign_id' => $campaign->id, 'user_id' => $request->user()->id],
                ['name' => $request->user()->name."'s Crew"],
            );
        }

        // Same idea for campaigns that predate the public join-link column —
        // every organizer gets a working link the first time they view the
        // page, no data migration needed.
        if (! $campaign->uuid) {
            $campaign->update(['uuid' => (string) Str::uuid()]);
        }

        $campaign->load([
            'organizer:id,name',
            'players.user:id,name',
            'crews:id,campaign_id,user_id,name,share_code,faction,keyword_1_id,keyword_2_id,scrip',
            'crews.user:id,name',
            'invitations' => fn ($q) => $q->pending(),
            'invitations.user:id,name,email',
        ]);

        $incompleteCrew = $campaign->crews()
            ->whereDoesntHave('arsenalModels', fn ($q) => $q->whereNull('annihilated_at')->whereNull('removed_at'))
            ->exists();

        return inertia('Campaigns/Show', [
            'campaign' => $campaign,
            'is_organizer' => $request->user()->can('update', $campaign),
            'all_arsenals_complete' => ! $incompleteCrew,
            'active_solo_game' => $campaign->is_solo ? $this->activeSoloGame($campaign, $request->user()->id) : null,
        ]);
    }

    /**
     * The user's own not-yet-finished live game for this solo campaign, if
     * one exists — Play Live otherwise mints a brand new Game every click,
     * orphaning whatever the player was mid-way through. Lets the hub offer
     * "Resume" instead of silently abandoning it.
     *
     * @return array{uuid: string, status: string, started_at: string|null}|null
     */
    private function activeSoloGame(Campaign $campaign, int $userId): ?array
    {
        $game = Game::query()
            ->active()
            ->where('creator_id', $userId)
            ->where('is_solo', true)
            ->whereHas('campaignGame', fn ($q) => $q->where('campaign_id', $campaign->id))
            ->latest('id')
            ->first(['id', 'uuid', 'status', 'started_at']);

        return $game ? [
            'uuid' => $game->uuid,
            'status' => $game->status->value,
            'started_at' => $game->started_at?->toIso8601String(),
        ] : null;
    }

    public function settings(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        return inertia('Campaigns/Settings', [
            'campaign' => $campaign->only([
                'id', 'name', 'length_weeks', 'current_week', 'status',
                'optional_rules', 'competitive', 'weekly_event_active', 'is_solo',
                'started_at', 'ended_at',
            ]),
        ]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign)
    {
        $campaign->update($request->validated());

        return redirect()->route('campaigns.show', $campaign)
            ->withMessage('Campaign updated.');
    }

    public function start(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        if ($campaign->status !== CampaignStatusEnum::Planning) {
            return redirect()->back()->withMessage('Campaign already started.');
        }

        // Solo campaigns deliberately bypass the 2-player check — the single
        // organizer plays games offline and logs results manually.
        if (! $campaign->is_solo && $campaign->players()->count() < 2) {
            return redirect()->back()->withMessage(
                'Need at least 2 players before the campaign can start.',
                null,
                \App\Enums\MessageTypeEnum::error,
            );
        }

        // Require Starting Arsenal completion before starting. For solo, the
        // organizer's crew must have at least one arsenal model. For multiplayer,
        // every crew must have completed their arsenal.
        $incompleteCrew = $campaign->crews()
            ->whereDoesntHave('arsenalModels', fn ($q) => $q->whereNull('annihilated_at')->whereNull('removed_at'))
            ->exists();

        if ($incompleteCrew) {
            $message = $campaign->is_solo
                ? 'Complete your Starting Arsenal before starting the campaign.'
                : 'All players must complete their Starting Arsenal before the campaign can start.';

            return redirect()->back()->withMessage($message, null, \App\Enums\MessageTypeEnum::error);
        }

        $campaign->update([
            'status' => CampaignStatusEnum::Active,
            'started_at' => now(),
        ]);

        return redirect()->route('campaigns.show', $campaign)
            ->withMessage("{$campaign->name} is now active.");
    }

    public function end(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $campaign->update([
            'status' => CampaignStatusEnum::Ended,
            'ended_at' => now(),
        ]);

        return redirect()->route('campaigns.show', $campaign)
            ->withMessage("{$campaign->name} ended.");
    }

    public function destroy(Request $request, Campaign $campaign)
    {
        $this->authorize('delete', $campaign);

        $name = $campaign->name;
        $campaign->delete();

        return redirect()->route('campaigns.index')->withMessage("{$name} deleted.");
    }

    /**
     * Public, reusable "anyone with this link can join" invite — bound by
     * {campaign:uuid}, not the campaign's normal integer id. Unlike
     * CampaignInvitation (single-use, per-recipient token), this link stays
     * valid until the organizer regenerates it.
     */
    public function joinPublic(Request $request, Campaign $campaign)
    {
        if (! $request->user()) {
            session()->put('url.intended', route('campaigns.join', $campaign->uuid));

            return redirect()->route('login')
                ->with('message', 'Please log in or create an account to join this campaign.')
                ->with('messageType', 'warning');
        }

        if ($campaign->is_solo) {
            return redirect()->route('campaigns.index')
                ->withMessage('This is a solo campaign and can\'t be joined.', null, MessageTypeEnum::error);
        }

        if ($campaign->status === CampaignStatusEnum::Ended) {
            return redirect()->route('campaigns.index')
                ->withMessage('This campaign has ended.', null, MessageTypeEnum::error);
        }

        $isMember = $campaign->players()->where('user_id', $request->user()->id)->exists();
        if ($isMember) {
            return redirect()->route('campaigns.show', $campaign);
        }

        $this->addCampaignMember($campaign, $request->user());

        return redirect()->route('campaigns.show', $campaign)
            ->withMessage("Welcome to {$campaign->name}!");
    }

    /**
     * A multiplayer campaign only auto-stubs a `CampaignCrew` for the
     * organizer on solo campaigns (`store()`) — for multiplayer ones the
     * organizer is a `CampaignPlayer` from creation but has no path to a
     * crew of their own, since `joinPublic()`/invitation-accept both
     * short-circuit once the user is already a member. Requires already
     * being a member (organizer or invited player) — this isn't a second
     * "join" path, just backfilling the crew half of membership.
     */
    public function joinAsPlayer(Request $request, Campaign $campaign)
    {
        $isMember = $campaign->players()->where('user_id', $request->user()->id)->exists();
        if (! $isMember) {
            abort(403);
        }

        if ($campaign->is_solo) {
            return redirect()->route('campaigns.show', $campaign)
                ->withMessage('This is a solo campaign — you already have a crew.', null, MessageTypeEnum::error);
        }

        if ($campaign->status === CampaignStatusEnum::Ended) {
            return redirect()->route('campaigns.show', $campaign)
                ->withMessage('This campaign has ended.', null, MessageTypeEnum::error);
        }

        CampaignCrew::firstOrCreate(
            ['campaign_id' => $campaign->id, 'user_id' => $request->user()->id],
            ['name' => $request->user()->name."'s Crew"],
        );

        return redirect()->route('campaigns.show', $campaign)
            ->withMessage("You're all set — build your crew below!");
    }

    /**
     * Invalidates the current public join link by assigning a fresh uuid —
     * unlike a Game's uuid (hours-lived), a Campaign's link stays valid for
     * the life of the campaign (weeks), so a leaked link needs a way to be
     * revoked without deleting the campaign itself.
     */
    public function regenerateJoinLink(Request $request, Campaign $campaign)
    {
        $this->authorize('update', $campaign);

        $campaign->update(['uuid' => (string) Str::uuid()]);

        return redirect()->route('campaigns.show', $campaign)
            ->withMessage('Invite link regenerated — the old link no longer works.');
    }
}
