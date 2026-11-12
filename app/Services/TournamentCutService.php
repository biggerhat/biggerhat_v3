<?php

namespace App\Services;

use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use Illuminate\Support\Facades\DB;

/**
 * Single-elimination top cut played after the Swiss rounds.
 *
 * The cut is seeded from the Swiss standings (1 plays N, 2 plays N-1, ...) and laid out as a bracket: game k of a
 * round feeds game floor(k/2) of the next. Cut rounds are ordinary rounds flagged `is_cut`, so scenarios, scoring,
 * score entry, self-reporting and the scoring desk all work on them; their games are simply kept out of the Swiss
 * standings (see TournamentStandingsService). A tied game cannot advance anyone, so score entry refuses ties in a
 * cut round (TournamentScoreRecorder); should one slip through, the better seed advances.
 */
class TournamentCutService
{
    public const SIZES = [2, 4, 8, 16];

    public function __construct(private readonly TournamentStandingsService $standings) {}

    /**
     * Cut sizes this event could run right now (empty when a cut cannot start).
     *
     * @return list<int>
     */
    public function availableSizes(Tournament $tournament): array
    {
        if ($this->blockReason($tournament) !== null) {
            return [];
        }
        $qualifiers = count($this->seeds($tournament));

        return array_values(array_filter(self::SIZES, fn (int $size) => $size <= $qualifiers));
    }

    /** Why a cut cannot start now, or null when it can. */
    public function blockReason(Tournament $tournament): ?string
    {
        if ($tournament->status !== TournamentStatusEnum::Active) {
            return 'The tournament must be running.';
        }
        if ($tournament->rounds()->where('is_cut', true)->exists()) {
            return 'The top cut has already started.';
        }
        $swiss = $tournament->rounds()->where('is_cut', false)->get();
        if ($swiss->isEmpty() || $swiss->contains(fn (TournamentRound $r) => $r->status !== TournamentRoundStatusEnum::Completed)) {
            return 'Finish every Swiss round before starting the top cut.';
        }
        if (count($this->seeds($tournament)) < self::SIZES[0] * 2) {
            return 'A top cut needs at least 4 players still in the event.';
        }

        return null;
    }

    /**
     * Create the first cut round, paired from the Swiss standings.
     */
    public function start(Tournament $tournament, int $size): TournamentRound
    {
        return DB::transaction(function () use ($tournament, $size): TournamentRound {
            $locked = Tournament::lockForUpdate()->findOrFail($tournament->id);
            if ($reason = $this->blockReason($locked)) {
                throw new CutException($reason);
            }
            if (! in_array($size, $this->availableSizes($locked), true)) {
                throw new CutException('That cut size is not available for this field.');
            }

            $seeds = array_slice($this->seeds($locked), 0, $size);
            $order = $this->bracketOrder($size);
            $pairs = [];
            for ($i = 0; $i < $size; $i += 2) {
                $pairs[] = [$seeds[$order[$i] - 1], $seeds[$order[$i + 1] - 1]];
            }

            $locked->update(['cut_size' => $size]);

            return $this->createRound($locked, $pairs);
        });
    }

    /**
     * Pair the winners of the latest cut round into the next one.
     */
    public function advance(Tournament $tournament): TournamentRound
    {
        return DB::transaction(function () use ($tournament): TournamentRound {
            $locked = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $last = $locked->rounds()->where('is_cut', true)->reorder('round_number', 'desc')->with('games')->first();
            if ($last === null) {
                throw new CutException('There is no top cut to advance.');
            }
            if ($last->status !== TournamentRoundStatusEnum::Completed) {
                throw new CutException('Finish the current cut round first.');
            }
            $games = $last->games->where('is_bye', false)->values();
            if ($games->count() < 2) {
                throw new CutException('The final has been played — finalize the tournament.');
            }
            if ($locked->rounds()->where('round_number', '>', $last->round_number)->exists()) {
                throw new CutException('The next round already exists.');
            }

            $seedOf = array_flip($this->seeds($locked));
            $winners = $games->map(fn (TournamentGame $g) => $this->winnerId($g, $seedOf));
            if ($winners->contains(null)) {
                throw new CutException('Every cut game needs a winner before the bracket can advance.');
            }

            $pairs = [];
            foreach ($winners->chunk(2) as $pair) {
                $pairs[] = [$pair->first(), $pair->last()];
            }

            return $this->createRound($locked, $pairs);
        });
    }

    /**
     * The winner of a cut game, or null if it has no result. A tie goes to the better Swiss seed.
     *
     * @param  array<int, int>  $seedOf  player id => seed index (0 = top seed)
     */
    public function winnerId(TournamentGame $game, array $seedOf): ?int
    {
        if ($game->result === TournamentGameResultEnum::Pending || $game->player_two_id === null) {
            return $game->player_two_id === null ? $game->player_one_id : null;
        }
        if ($game->is_forfeit && $game->forfeit_player_id !== null) {
            return $game->forfeit_player_id === $game->player_one_id ? $game->player_two_id : $game->player_one_id;
        }
        if ($game->player_one_vp !== $game->player_two_vp) {
            return $game->player_one_vp > $game->player_two_vp ? $game->player_one_id : $game->player_two_id;
        }

        return ($seedOf[$game->player_one_id] ?? PHP_INT_MAX) <= ($seedOf[$game->player_two_id] ?? PHP_INT_MAX) ? $game->player_one_id : $game->player_two_id;
    }

    /**
     * Player ids still eligible for the cut, best Swiss placing first (not dropped, disqualified or a ringer).
     *
     * @return list<int>
     */
    public function seeds(Tournament $tournament): array
    {
        return collect($this->standings->computeSwiss($tournament))
            ->filter(fn (array $row) => ! $row['is_ringer'] && ! $row['is_dropped'])
            ->pluck('player_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Seed numbers in bracket order, so game k of a round feeds game floor(k/2) of the next: 8 => 1,8,4,5,2,7,3,6.
     *
     * @return list<int>
     */
    public function bracketOrder(int $size): array
    {
        $order = [1, 2];
        while (count($order) < $size) {
            $next = [];
            $total = count($order) * 2 + 1;
            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $total - $seed;
            }
            $order = $next;
        }

        return $order;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $pairs  player id pairs, in bracket order
     */
    private function createRound(Tournament $tournament, array $pairs): TournamentRound
    {
        $round = TournamentRound::create([
            'tournament_id' => $tournament->id,
            'round_number' => (int) $tournament->rounds()->max('round_number') + 1,
            'status' => TournamentRoundStatusEnum::Setup,
            'is_cut' => true,
        ]);

        $players = TournamentPlayer::whereIn('id', collect($pairs)->flatten())->get(['id', 'faction'])->keyBy('id');
        foreach ($pairs as $table => [$one, $two]) {
            TournamentGame::create([
                'tournament_round_id' => $round->id,
                'player_one_id' => $one,
                'player_two_id' => $two,
                'is_bye' => false,
                'is_manual' => false,
                'result' => TournamentGameResultEnum::Pending,
                'table_number' => $table + 1,
                'player_one_faction' => $players->get($one)?->getRawOriginal('faction'),
                'player_two_faction' => $players->get($two)?->getRawOriginal('faction'),
            ]);
        }

        return $round;
    }
}
