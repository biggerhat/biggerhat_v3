<?php

namespace App\Http\Controllers\Campaign;

use App\Enums\MessageTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Campaign\Campaign;
use App\Models\Campaign\CampaignArsenalModel;
use App\Models\Campaign\CampaignCrew;
use App\Models\CustomCharacter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Crew lifecycle endpoints — actions that mutate the crew at the meta-level
 * (rather than during a game or aftermath):
 *
 *   - annihilateLeader: applies the first-time Fate-intervention checkbox
 *     (pg 20) on initial leader annihilation, then retires the leader on the
 *     second occurrence.
 *   - startingAnew: scrap the current arsenal + leader and rebuild from
 *     scratch (pg 37). Player gets +5 scrip per week already elapsed.
 *   - scrapModel: Cut 'Em Up For Parts optional rule (pg 146) — annihilate
 *     a crew member for half its cost in scrip.
 */
class CrewLifecycleController extends Controller
{
    /**
     * Mark the current Leader as annihilated this aftermath. Behavior:
     *   - If this is the first annihilation in the campaign (miraculous
     *     recovery unused), set the flag and KEEP the leader (with prior
     *     injuries retained — pg 20 "the previous two injuries remain").
     *   - Else retire the leader (current=false, annihilated_at) and require
     *     Starting Anew to rebuild.
     */
    public function annihilateLeader(Request $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureOwner($request, $campaign, $crew);

        $leaderId = CustomCharacter::query()
            ->where('campaign_crew_id', $crew->id)
            ->where('is_campaign_leader', true)
            ->where('current', true)
            ->value('id');

        if (! $leaderId) {
            return redirect()->back()->withMessage(
                'No active leader to annihilate.',
                null,
                MessageTypeEnum::error,
            );
        }

        $survived = null;

        DB::transaction(function () use ($leaderId, &$survived) {
            $leader = CustomCharacter::query()->whereKey($leaderId)->lockForUpdate()->first();

            // Re-check under the lock — a concurrent request may have already
            // resolved this leader's annihilation attempt.
            if (! $leader || ! $leader->current) {
                return;
            }

            $survived = $leader->attemptAnnihilation();
        });

        if ($survived === null) {
            return redirect()->back()->withMessage(
                'That leader has already been resolved this attempt.',
                null,
                MessageTypeEnum::error,
            );
        }

        if ($survived) {
            return redirect()->back()->withMessage(
                'Fate intervened — your Leader survives (miraculous recovery used). Prior injuries remain.',
            );
        }

        return redirect()->route('campaigns.crews.arsenal.show', [$campaign, $crew->share_code])
            ->withMessage('Leader annihilated. Use Starting Anew to rebuild your crew.');
    }

    /**
     * Starting Anew (pg 37). Scrap the current arsenal + retire active leader,
     * grant 5 scrip per elapsed week, and route the player back to the Leader
     * Builder for a fresh start.
     */
    public function startingAnew(Request $request, Campaign $campaign, CampaignCrew $crew)
    {
        $this->ensureOwner($request, $campaign, $crew);

        $weeksElapsed = max(0, ($campaign->current_week ?? 1) - 1);
        $bonusScrip = 5 * $weeksElapsed;

        DB::transaction(function () use ($crew, $bonusScrip) {
            // Retire active leader + totem (preserve history rows).
            CustomCharacter::query()
                ->where('campaign_crew_id', $crew->id)
                ->where('current', true)
                ->update(['current' => false, 'replaced_at' => now()]);

            // Soft-delete the live arsenal models. We use removed_at rather
            // than hard delete so the history remains for the campaign log.
            CampaignArsenalModel::query()
                ->where('campaign_crew_id', $crew->id)
                ->whereNull('removed_at')
                ->whereNull('annihilated_at')
                ->update(['removed_at' => now()]);

            $crew->update([
                'starting_anew_at' => now(),
                'scrip' => $bonusScrip,
                'keyword_1_id' => null,
                'keyword_2_id' => null,
                'crew_card_effect_id' => null,
                'faction' => null,
            ]);
        });

        return redirect()->route('campaigns.crews.leader.edit', [$campaign, $crew->share_code])
            ->withMessage("Starting Anew — {$bonusScrip} bonus scrip granted. Build your new Leader.");
    }

    /**
     * Cut 'Em Up For Parts optional rule (pg 146). Annihilate a crew member
     * for ceil(cost / 2) scrip. Only available when the campaign has the
     * `cut_em_up` toggle enabled.
     */
    public function scrapModel(Request $request, Campaign $campaign, CampaignCrew $crew, CampaignArsenalModel $arsenalModel)
    {
        $this->ensureOwner($request, $campaign, $crew);

        if ($arsenalModel->campaign_crew_id !== $crew->id) {
            abort(404);
        }

        if (! ($campaign->optional_rules['cut_em_up'] ?? false)) {
            return redirect()->back()->withMessage(
                "Cut 'Em Up For Parts is not enabled on this campaign.",
                null,
                MessageTypeEnum::error,
            );
        }

        if ($arsenalModel->annihilated_at || $arsenalModel->removed_at) {
            return redirect()->back()->withMessage(
                'Model already removed from arsenal.',
                null,
                MessageTypeEnum::error,
            );
        }

        $cost = (int) ($arsenalModel->character->cost ?? 0);
        $scrip = (int) ceil($cost / 2);
        $scrapped = false;

        DB::transaction(function () use ($arsenalModel, $crew, $scrip, &$scrapped) {
            $locked = CampaignArsenalModel::query()->whereKey($arsenalModel->id)->lockForUpdate()->first();

            // Re-check under the lock — a concurrent scrap/annihilation on the
            // same model must not double-credit scrip.
            if (! $locked || $locked->annihilated_at || $locked->removed_at) {
                return;
            }

            $locked->update(['annihilated_at' => now()]);
            if ($scrip > 0) {
                $crew->increment('scrip', $scrip);
            }
            $scrapped = true;
        });

        if (! $scrapped) {
            return redirect()->back()->withMessage(
                'Model already removed from arsenal.',
                null,
                MessageTypeEnum::error,
            );
        }

        return redirect()->back()->withMessage("Scrapped for {$scrip} scrip.");
    }

    private function ensureOwner(Request $request, Campaign $campaign, CampaignCrew $crew): void
    {
        if ($crew->campaign_id !== $campaign->id) {
            abort(404);
        }
        if ($crew->user_id !== $request->user()->id && ! $request->user()->hasRole('super_admin')) {
            abort(403);
        }
    }
}
