<?php

namespace App\Http\Controllers\Game;

use App\Enums\GameStatusEnum;
use App\Events\GameFateChanged;
use App\Exceptions\FateException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Game\Concerns\BroadcastsGameEvents;
use App\Http\Requests\Games\Fate\FateArrangeRequest;
use App\Http\Requests\Games\Fate\FateCardRequest;
use App\Http\Requests\Games\Fate\FateCardsRequest;
use App\Http\Requests\Games\Fate\FateChooseRequest;
use App\Http\Requests\Games\Fate\FateCountRequest;
use App\Http\Requests\Games\Fate\FateDemandRequest;
use App\Http\Requests\Games\Fate\FateFlipRefRequest;
use App\Http\Requests\Games\Fate\FateFlipRequest;
use App\Http\Requests\Games\Fate\FatePutRequest;
use App\Http\Requests\Games\Fate\FateRefillRequest;
use App\Http\Requests\Games\Fate\FateRequest;
use App\Http\Requests\Games\Fate\FateRespondRequest;
use App\Http\Requests\Games\Fate\FateSlotRequest;
use App\Http\Requests\Games\Fate\FateTotalRequest;
use App\Models\Game;
use App\Models\GameFateFlip;
use App\Models\GamePlayer;
use App\Services\Fate\FateDeckService;
use App\Support\Fate\FateLog;
use App\Support\Tabletop\TabletopAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * The Fate deck and hands of a full game. Every action answers with the state the caller is allowed to see (their own hand, the public
 * piles, only counts for everyone else's hand), plus any private result of the action (the cards drawn or peeked at).
 *
 * Pre-release: behind the same switch as the tabletop (flag, or the beta permission).
 */
class GameFateController extends Controller
{
    use BroadcastsGameEvents;

    public function __construct(private readonly FateDeckService $fate) {}

    /** What the viewer may see. Participants see their own hand; observers of an observable game see the public state alone. */
    public function show(Request $request, Game $game): JsonResponse
    {
        $this->guard();
        abort_unless(Gate::allows('view', $game) || $game->is_observable, 403);

        return $this->answer($game);
    }

    /** The Fate history as readable lines. Safe for anyone who may see the table: the log never holds a hidden card. */
    public function log(Request $request, Game $game): JsonResponse
    {
        $this->guard();
        abort_unless(Gate::allows('view', $game) || $game->is_observable, 403);

        return response()->json(['log' => FateLog::lines($game)]);
    }

    public function demand(FateDemandRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => ['prompt' => $this->fate->demand($p, $request->validated('kind'), $request->validated('count'), $request->validated('note'))->id]);
    }

    public function randomDiscard(FateCountRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => ['discarded' => $this->fate->randomDiscard($p, $request->integer('n'))]);
    }

    public function respond(FateRespondRequest $request, Game $game, int $prompt): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->respond($p, $prompt, $request->boolean('show')));
    }

    public function dismiss(FateSlotRequest $request, Game $game, int $prompt): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->dismiss($p, $prompt));
    }

    public function draw(FateCountRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => ['drawn' => $this->fate->draw($p, $request->integer('n'))]);
    }

    public function refill(FateRefillRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => ['drawn' => $this->fate->refill($p, $request->validated('discard'))]);
    }

    public function discard(FateCardsRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->discardFromHand($p, $request->validated('cards')));
    }

    public function flip(FateFlipRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => ['flip' => $this->fate->flip($p, $request->integer('modifier'), $request->validated('label'), $request->validated('subject'), $request->validated('duel_role'), $request->validated('duel_with'))->id]);
    }

    /** Say what a duel flip's total is (the flip plus the stat), so the cheating order can be followed. */
    public function total(FateTotalRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->setTotal($p, $this->flipOf($game, $request->integer('flip')), $request->integer('total')));
    }

    public function choose(FateChooseRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->choose($p, $this->flipOf($game, $request->integer('flip')), $request->integer('card'), $request->validated('suit')));
    }

    public function cheat(FateChooseRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->cheat($p, $this->flipOf($game, $request->integer('flip')), $request->integer('card'), $request->validated('suit')));
    }

    public function declineCheat(FateFlipRefRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->declineCheat($p, $this->flipOf($game, $request->integer('flip'))));
    }

    public function resolve(FateFlipRefRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->resolve($p, $this->flipOf($game, $request->integer('flip'))));
    }

    public function shuffle(FateSlotRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->shuffleDiscard($p));
    }

    public function endPhase(FateSlotRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->endPhase($game));
    }

    public function remove(FateCardsRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->remove($p, $request->validated('cards')));
    }

    public function peek(FateCountRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => ['peeked' => $this->fate->peek($p, $request->integer('n'))]);
    }

    public function arrange(FateArrangeRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->arrange($p, $request->validated('decisions')));
    }

    public function put(FatePutRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->putOnDeck($p, $request->integer('card'), $request->validated('where')));
    }

    public function drawFromDiscard(FateCardRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->drawFromDiscard($p, $request->integer('card')));
    }

    public function search(FateSlotRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => ['deck' => $this->fate->searchView($p)]);
    }

    public function searchTake(FateCardRequest $request, Game $game): JsonResponse
    {
        return $this->act($request, $game, fn (GamePlayer $p) => $this->fate->searchTake($p, $request->integer('card')));
    }

    /**
     * Run an action as the acting player and answer with the caller's view of the table.
     *
     * @param  callable(GamePlayer): mixed  $do
     */
    private function act(FateRequest $request, Game $game, callable $do): JsonResponse
    {
        $this->guard();
        abort_unless($game->status === GameStatusEnum::InProgress, 422, 'Game not in progress');
        $player = $this->actor($game, $request->integer('slot') ?: null);

        try {
            $result = $do($player);
        } catch (FateException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // Tell everyone else to look again. The ping has no cards in it; they each ask for what they may see.
        $this->broadcastToOpponents($game, new GameFateChanged($game, (string) $request->route()?->getActionMethod()));

        return $this->answer($game, is_array($result) ? $result : []);
    }

    /** @param  array<string, mixed>  $private  results only the caller is told (the cards drawn, peeked at or searched) */
    private function answer(Game $game, array $private = []): JsonResponse
    {
        return response()->json([
            'state' => $this->fate->state($game, $this->fate->controlledPlayerIds($game, Auth::user())),
            ...$private,
        ]);
    }

    /** The player acting: your own slot, or in a solo game either slot you name (the creator plays both). */
    private function actor(Game $game, ?int $slot): GamePlayer
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        if ($game->is_solo) {
            abort_unless($game->creator_id === $user->id, 403, 'Not the solo game owner');

            /** @var GamePlayer */
            return $game->players()->where('slot', $slot ?? 1)->firstOrFail();
        }

        /** @var GamePlayer|null $player */
        $player = $game->players()->where('user_id', $user->id)->first();
        abort_unless($player !== null, 403);

        return $player;
    }

    private function flipOf(Game $game, int $id): GameFateFlip
    {
        return GameFateFlip::query()->where('game_id', $game->id)->findOrFail($id);
    }

    private function guard(): void
    {
        abort_unless(TabletopAccess::allows(Auth::user()), 404);
    }
}
