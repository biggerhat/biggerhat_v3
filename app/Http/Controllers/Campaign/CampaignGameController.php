<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\CampaignStatusEnum;
use App\Enums\DeploymentEnum;
use App\Enums\GameFormatEnum;
use App\Enums\GameRoleEnum;
use App\Enums\GameStatusEnum;
use App\Enums\MessageTypeEnum;
use App\Enums\PoolSeasonEnum;
use App\Http\Controllers\Controller;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignAftermath;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\Campaign\CampaignGame;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Scheme;
use App\Models\Strategy;
use App\Services\CampaignRules;
use App\Traits\Campaign\AuthorizesCampaignAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Creates a campaign-context Game record + wrapping `campaign_games` row, then
 * hands off to the existing `/games/{uuid}` tracker. The tracker reads Game
 * data unchanged; campaign-specific overlays (CR + ss-pool bonus banner)
 * attach via a sibling Inertia prop in a later iteration.
 *
 * Hiring constraint to arsenal contents is enforced at submit time only — the
 * existing CrewSelect step in GameSetupController doesn't know about campaigns,
 * so the constraint will land in a follow-up that hooks the campaign overlay
 * into that step.
 */
class CampaignGameController extends Controller
{
    use AuthorizesCampaignAccess;

    public function create(Request $request, Campaign $campaign)
    {
        $this->ensureCampaignMember($request, $campaign);

        $myCrew = CampaignCrew::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $request->user()->id)
            ->first();

        $opponents = CampaignCrew::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', '!=', $request->user()->id)
            ->with('user:id,name')
            ->get(['id', 'campaign_id', 'user_id', 'share_code', 'name', 'faction', 'scrip']);

        return inertia('Campaigns/NewGame', [
            'campaign' => $campaign->only(['id', 'name', 'status', 'current_week', 'length_weeks']),
            'my_crew' => $myCrew,
            'opponents' => $opponents,
            'my_arsenal_ss' => $myCrew ? $this->arsenalSs($myCrew) : 0,
            'my_cr' => $myCrew ? CampaignRules::campaignRating(0, 0, 0) : 0,
        ]);
    }

    public function store(Request $request, Campaign $campaign)
    {
        $this->ensureCampaignMember($request, $campaign);

        if ($campaign->status !== CampaignStatusEnum::Active) {
            return redirect()->back()->withMessage(
                'Campaign must be active to start a game.',
                null,
                MessageTypeEnum::error,
            );
        }

        // Solo campaigns route through logSolo() — they don't pair against an
        // opponent crew and bypass the live game tracker entirely.
        if ($campaign->is_solo) {
            abort(404);
        }

        $data = $request->validate([
            'opponent_crew_id' => ['required', 'integer', Rule::exists('campaign_crews', 'id')->where('campaign_id', $campaign->id)],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $myCrew = CampaignCrew::query()
            ->where('campaign_id', $campaign->id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $opponentCrew = CampaignCrew::query()->whereKey($data['opponent_crew_id'])->firstOrFail();

        if ($myCrew->id === $opponentCrew->id) {
            abort(422, 'Cannot play against yourself.');
        }

        $arsenalA = $this->arsenalSs($myCrew);
        $arsenalB = $this->arsenalSs($opponentCrew);
        $encounterSize = CampaignRules::maxEncounterSize($arsenalA, $arsenalB);

        // CR / ss-bonus placeholders (Phase 9 brings equipment + advancements
        // online; the formulas already exist).
        $crA = CampaignRules::campaignRating(0, 0, 0);
        $crB = CampaignRules::campaignRating(0, 0, 0);
        $ssBonus = CampaignRules::ssPoolBonusForLower($crA, $crB);

        // Generate scenario triple just like the standard flow (pg 19 — campaign
        // games use scenario).
        $seasonEnum = PoolSeasonEnum::cases()[0]; // First/default season
        $strategies = Strategy::forSeason($seasonEnum)->get();
        $schemes = Scheme::forSeason($seasonEnum)->get();
        $deployments = DeploymentEnum::cases();

        $strategy = $strategies->isNotEmpty() ? $strategies->random() : null;
        $deployment = $deployments[array_rand($deployments)];
        $schemePool = $schemes->count() >= 3
            ? $schemes->random(3)->pluck('id')->toArray()
            : $schemes->pluck('id')->toArray();

        $game = DB::transaction(function () use ($campaign, $data, $myCrew, $opponentCrew, $encounterSize, $crA, $crB, $ssBonus, $strategy, $deployment, $schemePool, $seasonEnum, $request) {
            $game = Game::create([
                'name' => $data['name'] ?? null,
                'encounter_size' => $encounterSize,
                'season' => $seasonEnum->value,
                'format' => GameFormatEnum::Campaign->value,
                'strategy_id' => $strategy?->id,
                'deployment' => $deployment->value,
                'scheme_pool' => $schemePool,
                'status' => GameStatusEnum::Setup,
                'creator_id' => $request->user()->id,
                'is_solo' => false,
            ]);

            // Random role assignment, like GameController::store.
            $roles = collect([GameRoleEnum::Attacker->value, GameRoleEnum::Defender->value])->shuffle();

            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $request->user()->id,
                'slot' => 1,
                'role' => null,
            ]);
            GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $opponentCrew->user_id,
                'slot' => 2,
                'role' => null,
            ]);

            CampaignGame::create([
                'campaign_id' => $campaign->id,
                'week_number' => $campaign->current_week,
                'crew_a_id' => $myCrew->id,
                'crew_b_id' => $opponentCrew->id,
                'base_game_id' => $game->id,
                'encounter_size' => $encounterSize,
                'cr_a' => $crA,
                'cr_b' => $crB,
                'ss_bonus_to_lower' => $ssBonus,
                'status' => 'setup',
            ]);

            return $game;
        });

        return redirect()->route('games.show', $game->uuid)
            ->withMessage("Campaign game created (encounter size {$encounterSize}ss).");
    }

    /**
     * Solo-mode game log form. The user plays a game offline (or on a vTT)
     * and comes back here to record the result so the Aftermath wizard can
     * mutate their arsenal. No live tracker, no opponent crew.
     */
    public function createSolo(Request $request, Campaign $campaign)
    {
        $this->ensureCampaignMember($request, $campaign);
        abort_unless($campaign->is_solo, 404);

        if ($campaign->status !== CampaignStatusEnum::Active) {
            return redirect()->route('campaigns.show', $campaign)->withMessage(
                'Campaign must be active to log a game.',
                null,
                MessageTypeEnum::error,
            );
        }

        // Self-heal: solo campaigns created before the auto-crew patch (or by a
        // future code path that forgets to mint one) get a crew stub here so
        // the page never 404s on the firstOrFail below.
        $myCrew = CampaignCrew::firstOrCreate(
            ['campaign_id' => $campaign->id, 'user_id' => $request->user()->id],
            ['name' => $request->user()->name."'s Crew"],
        );

        return inertia('Campaigns/LogGame', [
            'campaign' => $campaign->only(['id', 'name', 'status', 'current_week', 'length_weeks', 'is_solo']),
            'my_crew' => $myCrew,
            'my_arsenal_ss' => $this->arsenalSs($myCrew),
            'my_cr' => CampaignRules::campaignRating(0, 0, 0),
        ]);
    }

    public function storeSolo(Request $request, Campaign $campaign)
    {
        $this->ensureCampaignMember($request, $campaign);
        abort_unless($campaign->is_solo, 404);

        if ($campaign->status !== CampaignStatusEnum::Active) {
            return redirect()->back()->withMessage(
                'Campaign must be active to log a game.',
                null,
                MessageTypeEnum::error,
            );
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            // Player's own scoring + scheme/strategy progress.
            'vp_self' => ['required', 'integer', 'min:0', 'max:20'],
            'schemes_completed' => ['required', 'integer', 'min:0', 'max:3'],
            // Opponent VP is optional (some scenarios are co-op-like). Default 0.
            'vp_opponent' => ['nullable', 'integer', 'min:0', 'max:20'],
            'won' => ['required', 'boolean'],
            'withdrew' => ['sometimes', 'boolean'],
            // Turn is only meaningful when the player withdrew; reject the
            // inconsistent combo so downstream consumers can trust the row.
            'withdrew_turn' => ['nullable', 'required_if:withdrew,true', 'integer', 'min:1', 'max:10'],
        ]);

        $myCrew = CampaignCrew::firstOrCreate(
            ['campaign_id' => $campaign->id, 'user_id' => $request->user()->id],
            ['name' => $request->user()->name."'s Crew"],
        );

        $crA = CampaignRules::campaignRating(0, 0, 0);
        $arsenalA = $this->arsenalSs($myCrew);
        // Solo games skip the encounter-size negotiation. Use the arsenal size
        // directly so downstream UI has a reasonable number for the record.
        $encounterSize = max(20, min(50, $arsenalA + 6));

        $aftermath = DB::transaction(function () use ($campaign, $data, $myCrew, $encounterSize, $crA) {
            $won = (bool) ($data['won'] ?? false);

            $campaignGame = CampaignGame::create([
                'campaign_id' => $campaign->id,
                'week_number' => $campaign->current_week,
                'crew_a_id' => $myCrew->id,
                'crew_b_id' => null,
                'base_game_id' => null,
                'encounter_size' => $encounterSize,
                'cr_a' => $crA,
                'cr_b' => 0,
                'ss_bonus_to_lower' => 0,
                'winner_crew_id' => $won ? $myCrew->id : null,
                'withdrew_crew_id' => ! empty($data['withdrew']) ? $myCrew->id : null,
                'withdrew_turn' => $data['withdrew_turn'] ?? null,
                'vp_a' => $data['vp_self'],
                'vp_b' => $data['vp_opponent'] ?? 0,
                'schemes_completed_a' => $data['schemes_completed'],
                'schemes_completed_b' => 0,
                'status' => 'aftermath',
            ]);

            // GameObserver normally increments total_wins on game completion,
            // but solo games never create a base Game row — do it inline so
            // the campaign-hub Wins stat works in solo competitive mode too.
            if ($won) {
                CampaignCrew::query()->whereKey($myCrew->id)->increment('total_wins');
            }

            // Spawn the aftermath inline so the post-log redirect lands on a
            // GET (Aftermath show) rather than the POST `aftermaths.start`.
            return CampaignAftermath::create([
                'campaign_game_id' => $campaignGame->id,
                'campaign_crew_id' => $myCrew->id,
                'current_phase' => 1,
                'status' => 'open',
            ]);
        });

        return redirect()->route('campaigns.aftermaths.show', $aftermath->id)
            ->withMessage('Game logged. Walking through Aftermath now.');
    }

    private function arsenalSs(CampaignCrew $crew): int
    {
        return (int) CampaignArsenalModel::query()
            ->where('campaign_crew_id', $crew->id)
            ->active()
            ->join('characters', 'characters.id', '=', 'campaign_arsenal_models.character_id')
            ->sum('characters.cost');
    }
}
