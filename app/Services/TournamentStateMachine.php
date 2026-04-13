<?php

namespace App\Services;

use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
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

        return null;
    }

    private function guardStartTournament(Tournament $tournament): ?string
    {
        $missingFaction = $tournament->players()->whereNull('faction')->count();
        if ($missingFaction > 0) {
            return "{$missingFaction} player(s) missing faction selection";
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
        $missing = [];
        if (! $round->strategy_id) {
            $missing[] = 'strategy';
        }
        if (! $round->deployment) {
            $missing[] = 'deployment';
        }
        if (empty($round->scheme_pool) || count((array) $round->scheme_pool) < 3) {
            $missing[] = 'scheme pool';
        }
        if (! empty($missing)) {
            return 'Set the round scenario before starting (missing: '.implode(', ', $missing).')';
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
            ->filter(fn ($p) => ! $p->is_disqualified
                && ($p->dropped_after_round === null || $p->dropped_after_round >= $round->round_number)
                && ! isset($pairedIds[$p->id]))
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
