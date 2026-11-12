<?php

namespace App\Http\Controllers\Tournament;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournaments\TournamentCutStartRequest;
use App\Models\Tournament;
use App\Models\TournamentGame;
use App\Models\TournamentRound;
use App\Models\User;
use App\Notifications\Tournament\TournamentRoundPaired;
use App\Services\CutException;
use App\Services\TournamentCutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The single-elimination top cut: start it from the Swiss standings, then advance the winners round by round.
 * The cut rounds themselves are played, scored and ended like any other round.
 */
class TournamentCutController extends Controller
{
    use BroadcastsTournamentUpdates;

    public function __construct(private readonly TournamentCutService $cut) {}

    public function start(TournamentCutStartRequest $request, Tournament $tournament): JsonResponse
    {
        try {
            $round = $this->cut->start($tournament, (int) $request->validated()['size']);
        } catch (CutException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $this->announce($tournament, $round);

        return response()->json(['success' => true, 'round_id' => $round->id]);
    }

    public function advance(Tournament $tournament): JsonResponse
    {
        Gate::authorize('manage', $tournament);

        try {
            $round = $this->cut->advance($tournament);
        } catch (CutException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $this->announce($tournament, $round);

        return response()->json(['success' => true, 'round_id' => $round->id]);
    }

    /** Tell everyone in the new round where to sit, then refresh watchers. */
    private function announce(Tournament $tournament, TournamentRound $round): void
    {
        $games = $round->games()->with([
            'playerOne:id,user_id,display_name', 'playerOne.members:id,tournament_player_id,user_id',
            'playerTwo:id,user_id,display_name', 'playerTwo.members:id,tournament_player_id,user_id',
        ])->get();
        $users = User::whereIn('id', $games->flatMap(fn (TournamentGame $g) => [...$g->playerOne->linkedUserIds(), ...$g->playerTwo->linkedUserIds()])->unique())->get()->keyBy('id');

        foreach ($games as $game) {
            foreach ($game->playerOne->linkedUserIds() as $userId) {
                $users->get($userId)?->notify(new TournamentRoundPaired($tournament, $round, $game->playerTwo->display_name, $game->table_number));
            }
            foreach ($game->playerTwo->linkedUserIds() as $userId) {
                $users->get($userId)?->notify(new TournamentRoundPaired($tournament, $round, $game->playerOne->display_name, $game->table_number));
            }
        }

        $this->broadcastUpdate($tournament, 'cut_round_created');
    }
}
