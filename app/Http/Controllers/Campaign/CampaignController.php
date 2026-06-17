<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\Campaign\CampaignPlayerRoleEnum;
use App\Enums\Campaign\CampaignStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Campaign\UpdateCampaignRequest;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
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

        $campaign->load([
            'organizer:id,name',
            'players.user:id,name',
            'crews:id,campaign_id,user_id,name,share_code,faction,scrip',
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
        ]);
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
}
