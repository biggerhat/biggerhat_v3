<?php

namespace App\Services;

use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\TOS\Deployment;
use App\Models\TOS\Operation;
use App\Models\Tournament;
use App\Models\TournamentRound;

/**
 * Centralizes the rules for transitioning tournament + round status.
 *
 * Each `canTransitionTo*()` method returns null on success or a human-readable
 * error string on failure. This keeps the controllers thin and makes the
 * tournament/round lifecycle rules independently testable.
 */
class TournamentStateMachine
{
    /**
     * Validate a tournament-level status transition. Returns null if allowed,
     * otherwise a 422-friendly error message.
     */
    public function canTransitionTournamentTo(Tournament $tournament, TournamentStatusEnum $next): ?string
    {
        /** @var TournamentStatusEnum $current */
        $current = $tournament->status;

        if (! in_array($next, $current->validTransitions(), true)) {
            return 'Invalid status transition';
        }

        return match ($next) {
            TournamentStatusEnum::Active => $this->guardStartTournament($tournament),
            TournamentStatusEnum::Completed => $this->guardFinalizeTournament($tournament),
            default => null,
        };
    }

    /**
     * Validate a round-level status transition. Returns null if allowed,
     * otherwise a 422-friendly error message.
     */
    public function canTransitionRoundTo(TournamentRound $round, TournamentRoundStatusEnum $next): ?string
    {
        /** @var TournamentRoundStatusEnum $current */
        $current = $round->status;

        return match ($next) {
            TournamentRoundStatusEnum::InProgress => $this->guardStartRound($round, $current),
            TournamentRoundStatusEnum::Completed => $this->guardCompleteRound($round, $current),
            TournamentRoundStatusEnum::Setup => $current === TournamentRoundStatusEnum::Setup
                ? null
                : 'Round is already past setup',
        };
    }

    /**
     * Validate that a started round may be reset back to its pre-start state ("Oh crap" button).
     * Only a round in progress can be reset; a completed round's results are already final.
     */
    public function canResetRound(Tournament $tournament, TournamentRound $round): ?string
    {
        if ($tournament->status === TournamentStatusEnum::Completed) {
            return 'This tournament is completed, so its rounds are locked.';
        }

        if ($round->status !== TournamentRoundStatusEnum::InProgress) {
            return 'Only a round that is in progress can be reset.';
        }

        return null;
    }

    /**
     * Validate that the round's scenario fields (deployment / strategy /
     * scheme pool) may currently be edited. Returns null if allowed.
     *
     * - Setup: always editable
     * - InProgress: editable until the first non-bye game has been reported
     * - Completed: locked
     */
    public function canEditScenario(Tournament $tournament, TournamentRound $round): ?string
    {
        if ($round->status === TournamentRoundStatusEnum::Completed) {
            return 'Cannot modify scenario after round is completed';
        }
        if ($round->status === TournamentRoundStatusEnum::InProgress) {
            $hasReportedGames = $round->games()
                ->where('result', '!=', TournamentGameResultEnum::Pending)
                ->where('is_bye', false)
                ->exists();
            if ($hasReportedGames) {
                return 'Cannot modify scenario after games have been reported';
            }
        }

        return null;
    }

    /**
     * Validate that pairings can be (re)generated for this round.
     */
    public function canPairRound(Tournament $tournament, TournamentRound $round): ?string
    {
        if ($round->status !== TournamentRoundStatusEnum::Setup) {
            return 'Round must be in setup to generate pairings';
        }

        if ($round->is_cut) {
            return 'Top-cut pairings come from the bracket — they cannot be regenerated.';
        }

        if ($tournament->status !== TournamentStatusEnum::Active) {
            return $round->round_number === 1
                ? 'Tournament must be started before Round 1 can be paired'
                : 'Tournament must be active to pair rounds';
        }

        if ($round->round_number > 1) {
            /** @var TournamentRound|null $prev */
            $prev = $tournament->rounds()
                ->where('round_number', $round->round_number - 1)
                ->first();
            if (! $prev || $prev->status !== TournamentRoundStatusEnum::Completed) {
                return 'Round '.($round->round_number - 1).' must be completed first';
            }
        }

        // Scenario must be fully configured before pairings are generated —
        // the scenario drives tracker-game creation when the round starts,
        // and TOs expect to see the matchup scenario at pair time.
        if ($missing = $this->missingScenarioFields($tournament, $round)) {
            return 'Set the round scenario before pairing (missing: '.$missing.')';
        }

        return null;
    }

    /**
     * Comma-separated list of missing scenario fields, or null when the scenario is fully
     * configured. Which fields are required comes from the tournament's ruleset. Shared by
     * canPairRound + guardStartRound.
     */
    private function missingScenarioFields(Tournament $tournament, TournamentRound $round): ?string
    {
        $missing = [];
        foreach ($tournament->rulesetDefinition()->scenarioFields as $field) {
            $isMissing = match ($field) {
                'strategy' => ! $round->strategy_id,
                'deployment' => ! $round->deployment,
                'scheme_pool' => empty($round->scheme_pool) || count((array) $round->scheme_pool) < 3,
                'tos_operation' => ! ($id = $round->scenario['operation_id'] ?? null) || ! Operation::whereKey($id)->exists(),
                'tos_deployment' => ! ($id = $round->scenario['deployment_id'] ?? null) || ! Deployment::whereKey($id)->exists(),
                default => false,
            };
            if ($isMissing) {
                $missing[] = match ($field) {
                    'scheme_pool' => 'scheme pool',
                    'tos_operation' => 'operation',
                    'tos_deployment' => 'deployment',
                    default => $field,
                };
            }
        }

        return empty($missing) ? null : implode(', ', $missing);
    }

    private function guardStartTournament(Tournament $tournament): ?string
    {
        $ruleset = $tournament->rulesetDefinition();
        if ($ruleset->requiresDeclaration && $ruleset->declaration !== 'none') {
            $column = $ruleset->declaration === 'allegiance' ? 'allegiance_id' : 'faction';
            $noun = $ruleset->declaration === 'allegiance' ? 'allegiance' : 'faction';

            if ($tournament->isTeamEvent()) {
                // Each person on a team declares for themself; a team is ready when every seat is filled and declared.
                $incomplete = $tournament->players()->where('is_ringer', false)->withCount('members')
                    ->withCount(['members as undeclared_count' => fn ($q) => $q->whereNull($column)])
                    ->get()
                    ->filter(fn ($team) => (int) $team->getAttribute('members_count') < $tournament->team_size || (int) $team->getAttribute('undeclared_count') > 0)
                    ->count();
                if ($incomplete > 0) {
                    return "{$incomplete} team(s) incomplete — every member needs a name and a {$noun}";
                }
            } else {
                $missing = $tournament->players()->whereNull($column)->count();
                if ($missing > 0) {
                    return "{$missing} player(s) missing {$noun} selection";
                }
            }
        }

        if ($tournament->players()->count() < 2) {
            return 'Need at least 2 players to start';
        }

        return null;
    }

    private function guardFinalizeTournament(Tournament $tournament): ?string
    {
        if ($tournament->rounds()->count() === 0) {
            return 'No rounds have been played';
        }

        $incomplete = $tournament->rounds()
            ->where('status', '!=', TournamentRoundStatusEnum::Completed)
            ->count();
        if ($incomplete > 0) {
            return "{$incomplete} round(s) still in progress. Finish all rounds first.";
        }

        // A top cut has to be played through: finalizing after the semifinals would leave no champion.
        $last = $tournament->rounds()->reorder('round_number', 'desc')->with('games')->first();
        if ($last?->is_cut && $last->games->where('is_bye', false)->count() > 1) {
            return 'The top cut is not finished — advance the bracket and play the final first.';
        }

        return null;
    }

    private function guardStartRound(TournamentRound $round, TournamentRoundStatusEnum $current): ?string
    {
        if ($current !== TournamentRoundStatusEnum::Setup) {
            return 'Round must be in setup to start';
        }

        if ($round->games()->count() === 0) {
            return 'Generate pairings before starting the round';
        }

        // Scenario must be fully configured before the round starts — otherwise
        // the per-pairing Game tracker games are missing strategy/deployment/schemes.
        if ($missing = $this->missingScenarioFields($round->tournament, $round)) {
            return 'Set the round scenario before starting (missing: '.$missing.')';
        }

        // Cut rounds only involve the players still in the bracket, so nobody else needs a pairing.
        if ($round->is_cut) {
            return null;
        }

        // Every active player (non-DQ, not dropped before this round) must be in a pairing.
        $round->loadMissing(['games', 'tournament.players']);
        $pairedIds = [];
        foreach ($round->games as $g) {
            if ($g->player_one_id) {
                $pairedIds[$g->player_one_id] = true;
            }
            if ($g->player_two_id) {
                $pairedIds[$g->player_two_id] = true;
            }
        }
        $unpaired = $round->tournament->players
            ->filter(fn ($p) => $p->isActiveForRound($round->round_number) && ! isset($pairedIds[$p->id]))
            ->pluck('display_name')
            ->all();

        if (! empty($unpaired)) {
            $names = implode(', ', array_slice($unpaired, 0, 3));
            $more = count($unpaired) > 3 ? ' (+'.(count($unpaired) - 3).' more)' : '';

            return 'All active players must be paired (or assigned a bye) before starting — unpaired: '.$names.$more;
        }

        return null;
    }

    private function guardCompleteRound(TournamentRound $round, TournamentRoundStatusEnum $current): ?string
    {
        if ($current !== TournamentRoundStatusEnum::InProgress) {
            return 'Round must be in progress to finish';
        }

        $unreported = $round->games()
            ->where('result', TournamentGameResultEnum::Pending)
            ->count();
        if ($unreported > 0) {
            return "{$unreported} game(s) not yet reported. Report all games before finishing the round.";
        }

        return null;
    }
}
