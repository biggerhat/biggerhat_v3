<?php

namespace App\Services;

use App\Enums\TournamentGameResultEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use Illuminate\Support\Collection;

/**
 * Generates pairings for a tournament round.
 *
 * Round 1: random order, with same-meta avoidance (don't pair players from
 *   the same regional/community Meta unless mathematically forced).
 * Round 2+: Swiss — sorted by current standings, avoiding rematches.
 *
 * Pairing is constraint-based: a list of constraint closures is applied to
 * each opponent candidate; if no candidate satisfies all of them, we drop
 * the lowest-priority constraint and try again, repeating until we find a
 * pair (rematch with the same player is the implicit fallback when every
 * constraint has been relaxed).
 */
class TournamentPairingService
{
    public function __construct(private readonly TournamentStandingsService $standings) {}

    /**
     * Pure pairing algorithm — returns intended pairings without persisting.
     *
     * @param  array<int, true>  $alreadyPairedIds  player_id => true map of players already manually paired
     * @return array<int, array{player_one_id: int, player_two_id: int|null, is_bye: bool}>
     */
    public function generatePairings(Tournament $tournament, TournamentRound $round, array $alreadyPairedIds = []): array
    {
        $activePlayers = $this->getActivePlayers($tournament, $round->round_number);
        $ringer = $tournament->ringerPlayer();
        $previousOpponents = $this->getPreviousOpponents($tournament);
        $byeHistory = $this->getByeHistory($tournament);

        // Drop anyone already covered by a manual pairing.
        if (! empty($alreadyPairedIds)) {
            $activePlayers = $activePlayers->filter(fn (TournamentPlayer $p) => ! isset($alreadyPairedIds[$p->id]));
        }

        // Separate ringer from rankable; ringer only plays when count is odd.
        $playersToRank = $activePlayers->filter(fn (TournamentPlayer $p) => ! $p->is_ringer)->values();
        $needsRinger = $playersToRank->count() % 2 !== 0;
        $isFirstRound = (int) $round->round_number === 1;

        $playersToRank = $isFirstRound
            ? $playersToRank->shuffle()
            : $this->sortByStandings($tournament, $playersToRank);

        $pairings = [];
        $paired = [];

        if ($needsRinger) {
            $this->assignRingerOrBye(
                $playersToRank, $ringer, $previousOpponents, $byeHistory,
                $isFirstRound, $pairings, $paired,
            );
        }

        // Pair the remaining players.
        $unpaired = $playersToRank->filter(fn (TournamentPlayer $p) => ! isset($paired[$p->id]))->values();
        $used = [];

        for ($i = 0; $i < $unpaired->count(); $i++) {
            if (isset($used[$i])) {
                continue;
            }
            $playerA = $unpaired[$i];
            $j = $this->pickOpponentIndex($playerA, $unpaired, $used, $i + 1, $previousOpponents, $isFirstRound);
            if ($j === null) {
                continue; // genuinely unpairable (e.g. last player, no candidates)
            }
            $pairings[] = [
                'player_one_id' => $playerA->id,
                'player_two_id' => $unpaired[$j]->id,
                'is_bye' => false,
            ];
            $used[$i] = true;
            $used[$j] = true;
        }

        return $pairings;
    }

    /**
     * Replace the round's auto-paired games with freshly-generated pairings,
     * preserving any manual pairings the TO has already created. Returns the
     * number of new pairings created.
     *
     * Caller is responsible for the surrounding transaction + tracker-game
     * cleanup/recreation; this owns table numbering and faction snapshots.
     */
    public function regeneratePairings(Tournament $tournament, TournamentRound $round): int
    {
        // Survivors: manual games keep their slots and players.
        $manualGames = $round->games()->where('is_manual', true)->get();
        $alreadyPaired = [];
        $highestTable = 0;
        foreach ($manualGames as $g) {
            /** @var TournamentGame $g */
            if ($g->player_one_id) {
                $alreadyPaired[$g->player_one_id] = true;
            }
            if ($g->player_two_id) {
                $alreadyPaired[$g->player_two_id] = true;
            }
            if ($g->table_number !== null && $g->table_number > $highestTable) {
                $highestTable = $g->table_number;
            }
        }

        // Generate pairings for everyone not in a manual pairing.
        $pairings = $this->generatePairings($tournament, $round, $alreadyPaired);

        // Faction lookup for persistence. Do NOT use $tournament->load('players:...')
        // here — a column-subset load clobbers the relation and breaks the standings
        // pipeline (which trips strict-mode missing-attribute errors reading
        // is_disqualified / dropped_after_round / display_name). A separate local
        // query leaves $tournament->players untouched for downstream consumers.
        $playersById = $tournament->players()->get(['id', 'faction'])->keyBy('id');
        $tableNumber = $highestTable + 1;
        foreach ($pairings as $pairing) {
            TournamentGame::create([
                'tournament_round_id' => $round->id,
                'player_one_id' => $pairing['player_one_id'],
                'player_two_id' => $pairing['player_two_id'],
                'is_bye' => $pairing['is_bye'],
                'is_manual' => false,
                // Byes start as Pending and convert to Completed when the
                // round is started — keeps bye points off the standings
                // until the TO clicks Start Round, matching the manual
                // game scoring lifecycle.
                'result' => TournamentGameResultEnum::Pending,
                'table_number' => $pairing['is_bye'] ? null : $tableNumber++,
                'player_one_faction' => $playersById->get($pairing['player_one_id'])?->getRawOriginal('faction'),
                'player_two_faction' => $pairing['player_two_id']
                    ? $playersById->get($pairing['player_two_id'])?->getRawOriginal('faction')
                    : null,
            ]);
        }

        return count($pairings);
    }

    /**
     * Pick the index of the best available opponent for $playerA, applying
     * constraints in priority order. Strictness drops one constraint at a
     * time when no candidate satisfies the full set, so the absolute fallback
     * is "any unpaired player" (rematch acceptable).
     *
     * @param  Collection<int, TournamentPlayer>  $unpaired
     * @param  array<int, true>  $used  index => true
     * @param  array<int, int[]>  $previousOpponents  player_id => [opponent_ids]
     */
    private function pickOpponentIndex(
        TournamentPlayer $playerA,
        Collection $unpaired,
        array $used,
        int $startFrom,
        array $previousOpponents,
        bool $isFirstRound,
    ): ?int {
        $aOpponents = $previousOpponents[$playerA->id] ?? [];
        $aMeta = $playerA->effectiveMetaId();

        // Constraints in priority order — most-preferred first. PASS 1 applies all,
        // PASS 2 drops the LAST constraint, PASS 3 drops the next-to-last, etc.
        // Index 0 is always the highest-priority "no rematch" rule.
        $constraints = [
            // Avoid rematches.
            fn (TournamentPlayer $b): bool => ! in_array($b->id, $aOpponents, true),
        ];
        if ($isFirstRound && $aMeta !== null) {
            // Same-meta avoidance — only meaningful in Round 1.
            $constraints[] = fn (TournamentPlayer $b): bool => $b->effectiveMetaId() !== $aMeta;
        }

        for ($strictness = count($constraints); $strictness >= 0; $strictness--) {
            $active = array_slice($constraints, 0, $strictness);
            for ($j = $startFrom; $j < $unpaired->count(); $j++) {
                if (isset($used[$j])) {
                    continue;
                }
                $b = $unpaired[$j];
                $passes = true;
                foreach ($active as $c) {
                    if (! $c($b)) {
                        $passes = false;
                        break;
                    }
                }
                if ($passes) {
                    return $j;
                }
            }
        }

        return null;
    }

    /**
     * Assign the ringer (if there is one) to the lowest-standing player, or
     * award a bye to that player. Mutates $pairings + $paired.
     *
     * @param  Collection<int, TournamentPlayer>  $playersToRank
     * @param  array<int, int[]>  $previousOpponents
     * @param  array<int>  $byeHistory
     * @param  array<int, array{player_one_id: int, player_two_id: int|null, is_bye: bool}>  &$pairings
     * @param  array<int, true>  &$paired
     */
    private function assignRingerOrBye(
        Collection $playersToRank,
        ?TournamentPlayer $ringer,
        array $previousOpponents,
        array $byeHistory,
        bool $isFirstRound,
        array &$pairings,
        array &$paired,
    ): void {
        if ($ringer) {
            $ringerOpponent = $this->findRingerOpponent($playersToRank, $ringer, $previousOpponents, $isFirstRound);
            if ($ringerOpponent) {
                $pairings[] = [
                    'player_one_id' => $ringerOpponent->id,
                    'player_two_id' => $ringer->id,
                    'is_bye' => false,
                ];
                $paired[$ringerOpponent->id] = true;
                $paired[$ringer->id] = true;
            }

            return;
        }

        $byePlayer = $this->findByePlayer($playersToRank, $byeHistory, $isFirstRound);
        if ($byePlayer) {
            $pairings[] = [
                'player_one_id' => $byePlayer->id,
                'player_two_id' => null,
                'is_bye' => true,
            ];
            $paired[$byePlayer->id] = true;
        }
    }

    /**
     * Sort players by standings (best first) for Swiss pairing.
     *
     * @param  Collection<int, TournamentPlayer>  $players
     * @return Collection<int, TournamentPlayer>
     */
    private function sortByStandings(Tournament $tournament, Collection $players): Collection
    {
        $standingsMap = collect($this->standings->compute($tournament))->keyBy('player_id');

        return $players->sortBy(function (TournamentPlayer $p) use ($standingsMap) {
            $s = $standingsMap->get($p->id);
            if (! $s) {
                return [0, 0, 0];
            }

            // Negative for descending sort
            return [-$s['total_tp'], -$s['total_diff'], -$s['total_vp']];
        })->values();
    }

    /**
     * Get all active (non-DQ, not yet dropped) players for a given round,
     * with `user` eager-loaded so effectiveMetaId() doesn't trigger a
     * lazy-load (forbidden in strict mode).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TournamentPlayer>
     */
    private function getActivePlayers(Tournament $tournament, int $roundNumber): \Illuminate\Database\Eloquent\Collection
    {
        return $tournament->players() // @phpstan-ignore return.type
            ->with('user:id,meta_id')
            ->where('is_disqualified', false)
            ->where(function ($q) use ($roundNumber) {
                $q->whereNull('dropped_after_round')
                    ->orWhere('dropped_after_round', '>=', $roundNumber);
            })
            ->get();
    }

    /**
     * Build a map of player_id => [opponent_ids] from all previous rounds.
     *
     * @return array<int, int[]>
     */
    private function getPreviousOpponents(Tournament $tournament): array
    {
        $opponents = [];

        $games = $tournament->games()
            ->whereNotNull('player_two_id')
            ->where('is_bye', false)
            ->get(['tournament_games.player_one_id', 'tournament_games.player_two_id']);

        foreach ($games as $game) {
            /** @var TournamentGame $game */
            $opponents[$game->player_one_id][] = $game->player_two_id;
            $opponents[$game->player_two_id][] = $game->player_one_id;
        }

        return $opponents;
    }

    /**
     * Get IDs of players who have already received a bye.
     *
     * @return array<int>
     */
    private function getByeHistory(Tournament $tournament): array
    {
        return $tournament->games()
            ->where('is_bye', true)
            ->pluck('tournament_games.player_one_id')
            ->toArray();
    }

    /**
     * Find the lowest-standing player who hasn't played the ringer yet.
     *
     * @param  Collection<int, TournamentPlayer>  $players
     * @param  array<int, int[]>  $previousOpponents
     */
    private function findRingerOpponent(
        Collection $players,
        TournamentPlayer $ringer,
        array $previousOpponents,
        bool $isFirstRound,
    ): ?TournamentPlayer {
        $candidates = $isFirstRound ? $players->shuffle() : $players->reverse();
        $ringerOpponents = $previousOpponents[$ringer->id] ?? [];

        foreach ($candidates as $player) {
            if (! in_array($player->id, $ringerOpponents)) {
                return $player;
            }
        }

        return $candidates->first();
    }

    /**
     * Find the lowest-standing player who hasn't had a bye yet.
     *
     * @param  Collection<int, TournamentPlayer>  $players
     * @param  array<int>  $byeHistory
     */
    private function findByePlayer(
        Collection $players,
        array $byeHistory,
        bool $isFirstRound,
    ): ?TournamentPlayer {
        $candidates = $isFirstRound ? $players->shuffle() : $players->reverse();

        foreach ($candidates as $player) {
            if (! in_array($player->id, $byeHistory)) {
                return $player;
            }
        }

        return $candidates->first();
    }
}
