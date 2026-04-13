<?php

namespace App\Http\Controllers\Tournament;

use App\Enums\FactionEnum;
use App\Enums\TournamentGameResultEnum;
use App\Enums\TournamentRoundStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Services\TournamentTrackerGameFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TournamentGameController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function __construct(
        private readonly TournamentTrackerGameFactory $trackerGames,
    ) {}

    public function store(Request $request, Tournament $tournament, TournamentRound $round): JsonResponse
    {
        $this->authorize('manage', $tournament);

        $validated = $request->validate([
            'player_one_id' => ['required', 'exists:tournament_players,id'],
            'player_two_id' => ['nullable', 'exists:tournament_players,id'],
            'is_bye' => ['sometimes', 'boolean'],
            'table_number' => ['nullable', 'integer', 'min:1'],
        ]);

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

        if (! $isBye) {
            $this->trackerGames->createForRound($tournament, $round);
        }

        $this->broadcastUpdate($tournament, 'game_created');

        return response()->json(['success' => true, 'game' => $game]);
    }

    public function updateScore(Request $request, Tournament $tournament, TournamentGame $game): JsonResponse
    {
        $this->authorize('manage', $tournament);

        if ($game->round->status !== TournamentRoundStatusEnum::InProgress) {
            return response()->json([
                'error' => $game->round->status === TournamentRoundStatusEnum::Completed
                    ? 'Scores are locked after the round is completed'
                    : 'Start the round before entering scores',
            ], 422);
        }

        // Total VP is always derived from strategy_vp + scheme_vp — single source of truth.
        $validFactions = collect(FactionEnum::cases())->pluck('value')->all();
        $validated = $request->validate([
            'player_one_faction' => ['nullable', 'string', Rule::in($validFactions)],
            'player_one_master' => ['nullable', 'string', 'max:255'],
            'player_one_title' => ['nullable', 'string', 'max:255'],
            'player_one_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            'player_one_strategy_vp' => ['required', 'integer', 'min:0', 'max:5'],
            'player_one_scheme_vp' => ['required', 'integer', 'min:0', 'max:6'],
            'player_two_faction' => ['nullable', 'string', Rule::in($validFactions)],
            'player_two_master' => ['nullable', 'string', 'max:255'],
            'player_two_title' => ['nullable', 'string', 'max:255'],
            'player_two_crew_build_id' => ['nullable', 'exists:crew_builds,id'],
            'player_two_strategy_vp' => ['required', 'integer', 'min:0', 'max:5'],
            'player_two_scheme_vp' => ['required', 'integer', 'min:0', 'max:6'],
        ]);

        $validated['player_one_vp'] = $validated['player_one_strategy_vp'] + $validated['player_one_scheme_vp'];
        $validated['player_two_vp'] = $validated['player_two_strategy_vp'] + $validated['player_two_scheme_vp'];

        $game->update([
            ...$validated,
            'result' => TournamentGameResultEnum::Completed,
        ]);

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

    public function toggleForfeit(Request $request, Tournament $tournament, TournamentGame $game): JsonResponse
    {
        $this->authorize('manage', $tournament);

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
            $validated = $request->validate([
                'forfeit_player_id' => ['required', 'exists:tournament_players,id'],
            ]);

            $game->update([
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
