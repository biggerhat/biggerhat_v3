<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;

class TournamentPairingService
{
    public function __construct(private readonly TournamentStandingsService $standings) {}

    /**
     * Generate pairings for a round.
     * Round 1: random. Round 2+: Swiss (by standings, avoiding rematches).
     *
     * @return array<int, array{player_one_id: int, player_two_id: int|null, is_bye: bool}>
     */
    public function generatePairings(Tournament $tournament, TournamentRound $round): array
    {
        $activePlayers = $this->getActivePlayers($tournament, $round->round_number);
        $ringer = $tournament->ringerPlayer();
        $previousOpponents = $this->getPreviousOpponents($tournament);
        $byeHistory = $this->getByeHistory($tournament);

        // Separate ringer from active players for pairing logic
        $playersToRank = $activePlayers->filter(fn (TournamentPlayer $p) => ! $p->is_ringer)->values();
        $needsRinger = $playersToRank->count() % 2 !== 0;

        // For round 1: shuffle randomly. For round 2+: sort by standings.
        if ((int) $round->round_number === 1) {
            $playersToRank = $playersToRank->shuffle();
        } else {
            $standingsMap = collect($this->standings->compute($tournament))
                ->keyBy('player_id');

            $playersToRank = $playersToRank->sortBy(function (TournamentPlayer $p) use ($standingsMap) {
                $s = $standingsMap->get($p->id);
                if (! $s) {
                    return [0, 0, 0];
                }

                // Negative for descending sort
                return [-$s['total_tp'], -$s['total_diff'], -$s['total_vp']];
            })->values();
        }

        $pairings = [];
        $paired = [];

        // Handle odd number: assign bye or ringer
        if ($needsRinger) {
            if ($ringer) {
                // Ringer plays the lowest-standing player who hasn't played the ringer
                $ringerOpponent = $this->findRingerOpponent($playersToRank, $ringer, $previousOpponents, (int) $round->round_number === 1);
                if ($ringerOpponent) {
                    $pairings[] = [
                        'player_one_id' => $ringerOpponent->id,
                        'player_two_id' => $ringer->id,
                        'is_bye' => false,
                    ];
                    $paired[$ringerOpponent->id] = true;
                    $paired[$ringer->id] = true;
                }
            } else {
                // Assign bye to lowest-standing player who hasn't had one
                $byePlayer = $this->findByePlayer($playersToRank, $byeHistory, (int) $round->round_number === 1);
                if ($byePlayer) {
                    $pairings[] = [
                        'player_one_id' => $byePlayer->id,
                        'player_two_id' => null,
                        'is_bye' => true,
                    ];
                    $paired[$byePlayer->id] = true;
                }
            }
        }

        // Pair remaining players
        $unpaired = $playersToRank->filter(fn (TournamentPlayer $p) => ! isset($paired[$p->id]))->values();
        $used = [];

        for ($i = 0; $i < $unpaired->count(); $i++) {
            if (isset($used[$i])) {
                continue;
            }

            $playerA = $unpaired[$i];

            // Find best available opponent (next in standings who hasn't been played)
            for ($j = $i + 1; $j < $unpaired->count(); $j++) {
                if (isset($used[$j])) {
                    continue;
                }

                $playerB = $unpaired[$j];
                $aOpponents = $previousOpponents[$playerA->id] ?? [];

                // Prefer someone they haven't played
                if (! in_array($playerB->id, $aOpponents)) {
                    $pairings[] = [
                        'player_one_id' => $playerA->id,
                        'player_two_id' => $playerB->id,
                        'is_bye' => false,
                    ];
                    $used[$i] = true;
                    $used[$j] = true;
                    break;
                }
            }

            // If no non-rematch found, pair with next available (rematch as last resort)
            if (! isset($used[$i])) {
                for ($j = $i + 1; $j < $unpaired->count(); $j++) {
                    if (isset($used[$j])) {
                        continue;
                    }
                    $playerB = $unpaired[$j];
                    $pairings[] = [
                        'player_one_id' => $playerA->id,
                        'player_two_id' => $playerB->id,
                        'is_bye' => false,
                    ];
                    $used[$i] = true;
                    $used[$j] = true;
                    break;
                }
            }
        }

        return $pairings;
    }

    /**
     * Get all active (non-DQ, non-dropped) players for a given round.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, TournamentPlayer>
     */
    private function getActivePlayers(Tournament $tournament, int $roundNumber): \Illuminate\Database\Eloquent\Collection
    {
        return $tournament->players() // @phpstan-ignore return.type
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

        $games = TournamentGame::whereHas('round', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->whereNotNull('player_two_id')
            ->where('is_bye', false)
            ->get(['player_one_id', 'player_two_id']);

        foreach ($games as $game) {
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
        return TournamentGame::whereHas('round', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->where('is_bye', true)
            ->pluck('player_one_id')
            ->toArray();
    }

    /**
     * Find the lowest-standing player who hasn't played the ringer yet.
     */
    private function findRingerOpponent(
        \Illuminate\Support\Collection $players,
        TournamentPlayer $ringer,
        array $previousOpponents,
        bool $isFirstRound
    ): ?TournamentPlayer {
        $candidates = $isFirstRound ? $players->shuffle() : $players->reverse();
        $ringerOpponents = $previousOpponents[$ringer->id] ?? [];

        // Prefer someone who hasn't played the ringer
        foreach ($candidates as $player) {
            if (! in_array($player->id, $ringerOpponents)) {
                return $player;
            }
        }

        // Fallback: anyone
        return $candidates->first();
    }

    /**
     * Find the lowest-standing player who hasn't had a bye yet.
     */
    private function findByePlayer(
        \Illuminate\Support\Collection $players,
        array $byeHistory,
        bool $isFirstRound
    ): ?TournamentPlayer {
        $candidates = $isFirstRound ? $players->shuffle() : $players->reverse();

        // Prefer someone who hasn't had a bye
        foreach ($candidates as $player) {
            if (! in_array($player->id, $byeHistory)) {
                return $player;
            }
        }

        // Fallback: anyone (everyone has had a bye already)
        return $candidates->first();
    }
}
