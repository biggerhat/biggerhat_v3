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
        foreach ($this->matchPlayers($unpaired, $previousOpponents, $isFirstRound) as [$playerA, $playerB]) {
            $pairings[] = [
                'player_one_id' => $playerA->id,
                'player_two_id' => $playerB->id,
                'is_bye' => false,
            ];
        }

        return $isFirstRound ? $this->separateSameMeta($pairings, $playersToRank->keyBy('id')) : $pairings;
    }

    /**
     * The greedy first-fit pass can burn the only players who could split up a
     * same-meta pair (e.g. A1, A2, B1, C1 → B1 meets C1, A1 is stuck with A2).
     * Repair those by swapping partners with another pair when that leaves
     * both pairs clash-free. Pairs that include the ringer or a bye are left alone.
     *
     * @param  array<int, array{player_one_id: int, player_two_id: int|null, is_bye: bool}>  $pairings
     * @param  Collection<int, TournamentPlayer>  $players  rankable players keyed by id
     * @return array<int, array{player_one_id: int, player_two_id: int|null, is_bye: bool}>
     */
    private function separateSameMeta(array $pairings, Collection $players): array
    {
        $clash = function (int $x, int $y) use ($players): bool {
            $meta = $players->get($x)?->effectiveMetaId();

            return $meta !== null && $meta === $players->get($y)?->effectiveMetaId();
        };
        $swappable = fn (array $p): bool => ! $p['is_bye']
            && $p['player_two_id'] !== null
            && $players->has($p['player_one_id'])
            && $players->has($p['player_two_id']);

        foreach (array_keys($pairings) as $i) {
            if (! $swappable($pairings[$i]) || ! $clash($pairings[$i]['player_one_id'], $pairings[$i]['player_two_id'])) {
                continue;
            }
            [$a, $b] = [$pairings[$i]['player_one_id'], $pairings[$i]['player_two_id']];

            foreach (array_keys($pairings) as $k) {
                if ($k === $i || ! $swappable($pairings[$k])) {
                    continue;
                }
                [$c, $d] = [$pairings[$k]['player_one_id'], $pairings[$k]['player_two_id']];

                foreach ([[$c, $d], [$d, $c]] as [$x, $y]) {
                    // a meets x, b meets y — both new pairs must be clash-free.
                    if (! $clash($a, $x) && ! $clash($b, $y)) {
                        $pairings[$i]['player_two_id'] = $x;
                        $pairings[$k] = ['player_one_id' => $b, 'player_two_id' => $y, 'is_bye' => false];

                        continue 3;
                    }
                }
            }
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

    /** Search-node budget per strictness level; keeps worst-case pairing time bounded on big fields. */
    private const MATCH_NODE_BUDGET = 50000;

    /**
     * Pair the (standings-ordered) players into a perfect matching, preferring opponents close in
     * the standings but never at the price of a rematch when a rematch-free pairing exists.
     *
     * A greedy "first legal opponent" pass can strand the last players with each other even though
     * swapping an earlier choice would avoid it, so this backtracks. Constraints are relaxed one
     * level at a time (no rematch + no same meta → no rematch → anything); the first level with a
     * complete matching wins, and the search order keeps pairings as close in rank as possible.
     * With an odd count the lowest-ranked player is left unpaired (as before).
     *
     * @param  Collection<int, TournamentPlayer>  $players  best standing first
     * @param  array<int, int[]>  $previousOpponents  player_id => [opponent_ids]
     * @return list<array{0: TournamentPlayer, 1: TournamentPlayer}>
     */
    private function matchPlayers(Collection $players, array $previousOpponents, bool $isFirstRound): array
    {
        $list = $players->values()->all();
        $count = count($list) - (count($list) % 2);
        $list = array_slice($list, 0, $count);

        $noRematch = fn (TournamentPlayer $a, TournamentPlayer $b): bool => ! in_array($b->id, $previousOpponents[$a->id] ?? [], true);
        $differentMeta = fn (TournamentPlayer $a, TournamentPlayer $b): bool => $a->effectiveMetaId() === null || $a->effectiveMetaId() !== $b->effectiveMetaId();

        $levels = $isFirstRound
            ? [fn ($a, $b) => $noRematch($a, $b) && $differentMeta($a, $b), $noRematch]
            : [$noRematch];
        $levels[] = fn () => true;

        foreach ($levels as $allowed) {
            $budget = self::MATCH_NODE_BUDGET;
            $used = [];
            $pairs = $this->searchMatching($list, $used, $allowed, $budget);
            if ($pairs !== null) {
                return $pairs;
            }
        }

        return []; // unreachable: the last level allows every pairing
    }

    /**
     * @param  list<TournamentPlayer>  $list
     * @param  array<int, true>  $used  index => true
     * @param  callable(TournamentPlayer, TournamentPlayer): bool  $allowed
     * @return list<array{0: TournamentPlayer, 1: TournamentPlayer}>|null
     */
    private function searchMatching(array $list, array &$used, callable $allowed, int &$budget): ?array
    {
        if ($budget-- <= 0) {
            return null;
        }

        $i = null;
        foreach (array_keys($list) as $k) {
            if (! isset($used[$k])) {
                $i = $k;
                break;
            }
        }
        if ($i === null) {
            return [];
        }

        $used[$i] = true;
        for ($j = $i + 1; $j < count($list); $j++) {
            if (isset($used[$j]) || ! $allowed($list[$i], $list[$j])) {
                continue;
            }
            $used[$j] = true;
            $rest = $this->searchMatching($list, $used, $allowed, $budget);
            unset($used[$j]);
            if ($rest !== null) {
                return [[$list[$i], $list[$j]], ...$rest];
            }
            if ($budget <= 0) {
                break;
            }
        }
        unset($used[$i]);

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
        // `rank` already follows the ruleset's tiebreaker order (TP then SoS/DIFF/VP as the ruleset says) and gives
        // level players a joint rank, so pairing orders by it instead of assuming Gaining Grounds' TP → DIFF → VP.
        $rankByPlayer = collect($this->standings->compute($tournament))->pluck('rank', 'player_id');
        $unranked = $rankByPlayer->count() + 1;

        // Shuffle first: the sort is stable, so players level on every tiebreaker end up in random order
        // (the Gaining Grounds / Fields of Glory packets pair ties randomly).
        return $players->shuffle()->sortBy(fn (TournamentPlayer $p) => $rankByPlayer->get($p->id) ?? $unranked)->values();
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
