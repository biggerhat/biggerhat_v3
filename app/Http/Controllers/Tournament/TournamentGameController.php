<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\GameStatusEnum;
use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\ScoreTournamentGameRequest;
use App\Http\Requests\Tournaments\TournamentGameStoreRequest;
use App\Http\Requests\Tournaments\TournamentGameSwapRequest;
use App\Http\Requests\Tournaments\TournamentGameSwapTablesRequest;
use App\Http\Requests\Tournaments\TournamentGameToggleForfeitRequest;
use App\Models\Game;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Services\TournamentScoreRecorder;
use App\Services\TournamentTrackerGameFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentGameController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function __construct(
        private readonly TournamentTrackerGameFactory $trackerGames,
        private readonly TournamentScoreRecorder $scores,
    ) {}

    public function store(TournamentGameStoreRequest $request, Tournament $tournament, TournamentRound $round): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validated();

        $isBye = $validated['is_bye'] ?? false;
        $playerOne = TournamentPlayer::find($validated['player_one_id']);
        $playerTwo = isset($validated['player_two_id']) ? TournamentPlayer::find($validated['player_two_id']) : null;

        $tableNumber = $validated['table_number']
            ?? ($isBye ? null : ($round->games()->max('table_number') ?? 0) + 1);

        $game = TournamentGame::create([
            'tournament_round_id' => $round->id,
            'player_one_id' => $validated['player_one_id'],
            'player_two_id' => $validated['player_two_id'] ?? null,
            'is_bye' => $isBye,
            // Mark as manual so a subsequent Auto-Pair preserves it.
            'is_manual' => true,
            // Byes stay Pending until the round is Started — keeps bye
            // points off the standings until the TO actually starts.
            'result' => TournamentGameResultEnum::Pending,
            'table_number' => $tableNumber,
            'player_one_faction' => $playerOne?->getRawOriginal('faction'),
            'player_two_faction' => $playerTwo?->getRawOriginal('faction'),
        ]);

        // Only create the tracker Game row if the round is already Started —
        // otherwise the round-start transition handles creation for every
        // paired game at once. Adding games to a round that's still in Setup
        // used to pre-create tracker games before play even began.
        if (! $isBye && $round->status === TournamentRoundStatusEnum::InProgress) {
            $this->trackerGames->createForRound($tournament, $round);
        }

        $this->broadcastUpdate($tournament, 'game_created');

        return response()->json(['success' => true, 'game' => $game]);
    }

    /**
     * Fix a pairing during round setup: swap two players between pairings, or put an unpaired player in a slot
     * (the replaced player becomes unpaired). Touched pairings become manual so Auto Pair keeps them.
     * Returns a warning for any new pairing that is a rematch.
     */
    public function swapPlayers(TournamentGameSwapRequest $request, Tournament $tournament, TournamentRound $round): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validated();
        $slotId = fn (string $slot): string => $slot.'_id';
        $slotFaction = fn (string $slot): string => $slot.'_faction';

        $result = DB::transaction(function () use ($tournament, $round, $validated, $slotId, $slotFaction): array {
            $locked = TournamentRound::lockForUpdate()->find($round->id);
            if (! $locked || $locked->status !== TournamentRoundStatusEnum::Setup) {
                return ['error' => 'Pairings can only be changed while the round is in setup'];
            }

            $a = TournamentGame::where('tournament_round_id', $round->id)->find($validated['game_id']);
            $slotA = $validated['slot'];
            if (! $a || $a->{$slotId($slotA)} === null) {
                return ['error' => 'That pairing slot is empty'];
            }

            if (isset($validated['other_game_id'])) {
                $b = TournamentGame::where('tournament_round_id', $round->id)->find($validated['other_game_id']);
                $slotB = $validated['other_slot'];
                if (! $b || $b->{$slotId($slotB)} === null) {
                    return ['error' => 'That pairing slot is empty'];
                }
                if ($a->id === $b->id) {
                    return ['error' => 'Pick a player from a different pairing'];
                }

                [$a->{$slotId($slotA)}, $b->{$slotId($slotB)}] = [$b->{$slotId($slotB)}, $a->{$slotId($slotA)}];
                [$a->{$slotFaction($slotA)}, $b->{$slotFaction($slotB)}] = [$b->{$slotFaction($slotB)}, $a->{$slotFaction($slotA)}];
                $changed = [$a, $b];
            } else {
                $incoming = TournamentPlayer::where('tournament_id', $tournament->id)->find($validated['player_id']);
                if (! $incoming || $incoming->is_disqualified || $incoming->dropped_after_round !== null) {
                    return ['error' => 'That player is not available to pair'];
                }
                $alreadyPaired = TournamentGame::where('tournament_round_id', $round->id)
                    ->where(fn ($q) => $q->where('player_one_id', $incoming->id)->orWhere('player_two_id', $incoming->id))
                    ->exists();
                if ($alreadyPaired) {
                    return ['error' => 'That player is already paired this round'];
                }

                $a->{$slotId($slotA)} = $incoming->id;
                $a->{$slotFaction($slotA)} = $incoming->getRawOriginal('faction');
                $changed = [$a];
            }

            foreach ($changed as $game) {
                $game->is_manual = true;
                $game->save();
            }

            return ['games' => $changed];
        });

        if (isset($result['error'])) {
            return response()->json(['error' => $result['error']], 422);
        }

        $this->broadcastUpdate($tournament, 'pairings_changed');

        return response()->json(['success' => true, 'warnings' => $this->rematchWarnings($tournament, $round, $result['games'])]);
    }

    /**
     * Swap the table numbers of two pairings during round setup.
     */
    public function swapTables(TournamentGameSwapTablesRequest $request, Tournament $tournament, TournamentRound $round): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validated();

        $error = DB::transaction(function () use ($round, $validated): ?string {
            $locked = TournamentRound::lockForUpdate()->find($round->id);
            if (! $locked || $locked->status !== TournamentRoundStatusEnum::Setup) {
                return 'Tables can only be changed while the round is in setup';
            }

            $a = TournamentGame::where('tournament_round_id', $round->id)->find($validated['game_id']);
            $b = TournamentGame::where('tournament_round_id', $round->id)->find($validated['other_game_id']);
            if (! $a || ! $b) {
                return 'Both pairings must belong to this round';
            }

            [$a->table_number, $b->table_number] = [$b->table_number, $a->table_number];
            $a->is_manual = true;
            $b->is_manual = true;
            $a->save();
            $b->save();

            return null;
        });

        if ($error) {
            return response()->json(['error' => $error], 422);
        }

        $this->broadcastUpdate($tournament, 'pairings_changed');

        return response()->json(['success' => true]);
    }

    /**
     * @param  list<TournamentGame>  $games
     * @return list<string>
     */
    private function rematchWarnings(Tournament $tournament, TournamentRound $round, array $games): array
    {
        $warnings = [];
        foreach ($games as $game) {
            if ($game->player_one_id === null || $game->player_two_id === null) {
                continue;
            }
            $previous = TournamentGame::query()
                ->whereHas('round', fn ($q) => $q->where('tournament_id', $tournament->id)->where('id', '!=', $round->id))
                ->where(fn ($q) => $q
                    ->where(fn ($w) => $w->where('player_one_id', $game->player_one_id)->where('player_two_id', $game->player_two_id))
                    ->orWhere(fn ($w) => $w->where('player_one_id', $game->player_two_id)->where('player_two_id', $game->player_one_id)))
                ->with('round:id,round_number')
                ->first();
            if ($previous) {
                $names = TournamentPlayer::whereIn('id', [$game->player_one_id, $game->player_two_id])->pluck('display_name');
                $warnings[] = $names->join(' and ').' already played in Round '.$previous->round->round_number.'.';
            }
        }

        return $warnings;
    }

    public function updateScore(ScoreTournamentGameRequest $request, Tournament $tournament, TournamentGame $game): JsonResponse
    {
        if ($game->round->status !== TournamentRoundStatusEnum::InProgress) {
            return response()->json([
                'error' => $game->round->status === TournamentRoundStatusEnum::Completed
                    ? 'Scores are locked after the round is completed'
                    : 'Start the round before entering scores',
            ], 422);
        }

        $validated = $request->validated();

        // Guard: if a tracker game is attached and still running, make the TO
        // acknowledge they're finalizing ahead of the tracker. Tracker VP will
        // keep syncing into the record until the TO locks it in (Completed).
        $trackerGame = $game->trackerGame;
        if ($trackerGame
            && $trackerGame->status === GameStatusEnum::InProgress
            && empty($validated['confirm_override'])) {
            return response()->json([
                'error' => 'tracker_in_progress',
                'message' => 'The linked tracker game is still in progress. Confirm to finalize anyway.',
            ], 422);
        }

        unset($validated['confirm_override']);
        $agreed = (bool) ($validated['agreed'] ?? false);
        $one = $validated['player_one_score'];
        $two = $validated['player_two_score'];
        unset($validated['agreed'], $validated['player_one_score'], $validated['player_two_score']);

        // The status check above is a UX guard. Ending a round locks the round row, so take the same lock and
        // look again: a save racing "End Round" must not write a score into a round that just completed.
        $stale = false;
        DB::transaction(function () use ($game, $one, $two, $agreed, $validated, &$stale) {
            $round = TournamentRound::lockForUpdate()->find($game->tournament_round_id);
            if (! $round || $round->status !== TournamentRoundStatusEnum::InProgress) {
                $stale = true;

                return;
            }

            // Score components are ruleset-defined; the player's VP is their sum. Entering a score here
            // also supersedes any pending or disputed player report.
            $this->scores->record($game, $one, $two, $agreed, $validated);
        });

        if ($stale) {
            return response()->json(['error' => 'Scores are locked after the round is completed'], 422);
        }

        $this->broadcastUpdate($tournament, 'score_updated');

        return response()->json(['success' => true]);
    }

    public function destroy(Tournament $tournament, TournamentGame $game): JsonResponse
    {
        $this->authorize('manage', $tournament);

        if ($game->round->status !== TournamentRoundStatusEnum::Setup) {
            return response()->json(['error' => 'Can only remove games during round setup'], 422);
        }

        $this->trackerGames->destroyForGame($game);
        $game->delete();

        $this->broadcastUpdate($tournament, 'game_removed');

        return response()->json(['success' => true]);
    }

    /**
     * Force-close the linked tracker game from the TO side. Use when players
     * walked away mid-game (the tracker is stuck in_progress / partial-setup
     * forever) and the TO has already entered final scores on the tournament
     * side. Just flips the tracker status to Completed + completed_at —
     * doesn't fabricate a winner since tournament scoring is the source of
     * truth and the per-player turn snapshots are out of scope here.
     */
    public function completeTrackerGame(Request $request, Tournament $tournament, TournamentGame $game): JsonResponse
    {
        $this->authorize('manage', $tournament);

        if ($game->tournament_round_id && $game->round && $game->round->tournament_id !== $tournament->id) {
            return response()->json(['error' => 'Game does not belong to this tournament'], 403);
        }

        $trackerGame = $game->trackerGame;
        if (! $trackerGame) {
            return response()->json(['error' => 'No tracker game linked'], 422);
        }

        if ($trackerGame->status->isFinished()) {
            return response()->json(['error' => 'Tracker game is already finished'], 422);
        }

        // Re-check under lock so a concurrent abandon/complete isn't clobbered.
        $completed = DB::transaction(function () use ($trackerGame): bool {
            $locked = Game::lockForUpdate()->find($trackerGame->id);
            if (! $locked || $locked->status->isFinished()) {
                return false;
            }
            $locked->update(['status' => GameStatusEnum::Completed, 'completed_at' => now()]);

            return true;
        });

        if (! $completed) {
            return response()->json(['error' => 'Tracker game is already finished'], 422);
        }

        $this->broadcastUpdate($tournament, 'tracker_completed');

        return response()->json(['success' => true]);
    }

    public function toggleForfeit(TournamentGameToggleForfeitRequest $request, Tournament $tournament, TournamentGame $game): JsonResponse
    {
        $this->authorize('score', $tournament);

        if ($game->round->status !== TournamentRoundStatusEnum::InProgress) {
            return response()->json([
                'error' => $game->round->status === TournamentRoundStatusEnum::Completed
                    ? 'Cannot change forfeit after the round is completed'
                    : 'Start the round before assigning forfeits',
            ], 422);
        }

        if ($game->is_forfeit) {
            $game->update([
                'is_forfeit' => false,
                'forfeit_player_id' => null,
                'result' => TournamentGameResultEnum::Pending,
                'player_one_vp' => null,
                'player_two_vp' => null,
            ]);
        } else {
            $validated = $request->validated();

            $game->update([
                ...$this->scores->clearedReport(),
                'is_forfeit' => true,
                'forfeit_player_id' => $validated['forfeit_player_id'],
                'result' => TournamentGameResultEnum::Forfeited,
                'player_one_vp' => null,
                'player_two_vp' => null,
            ]);
        }

        $this->broadcastUpdate($tournament, 'forfeit_updated');

        return response()->json(['success' => true]);
    }
}
