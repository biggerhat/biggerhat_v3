<?php

namespace App\Http\Controllers\Game;

use App\Enums\GameStatusEnum;
use App\Enums\MessageTypeEnum;
use App\Events\GameDeployChanged;
use App\Events\GameStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Games\Deployment\ChooseFirstGroupRequest;
use App\Http\Requests\Games\Deployment\FinishPlacingRequest;
use App\Http\Requests\Games\Deployment\SkipDeploymentRequest;
use App\Http\Requests\Games\Deployment\SplitCrewRequest;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Support\Game\DeploymentRefused;
use App\Support\Game\GameDeployment;
use App\Support\Game\SafeBroadcast;
use App\Support\Tabletop\TabletopAccess;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Deployment on the tabletop (the Deployment step of a tabletop game's setup): the defender divides their crew, the attacker chooses
 * which group goes first, and the crews are placed in the rulebook's order. See {@see GameDeployment}.
 * Every action re-reads the game under a lock, so a stale or double request changes nothing.
 */
class GameDeployController extends Controller
{
    public function split(SplitCrewRequest $request, Game $game): RedirectResponse
    {
        $later = array_map('intval', $request->validated('later'));

        return $this->act($game, true, fn (Game $locked) => GameDeployment::split($locked, $later));
    }

    public function choose(ChooseFirstGroupRequest $request, Game $game): RedirectResponse
    {
        $first = (string) $request->validated('first');

        return $this->act($game, true, fn (Game $locked) => GameDeployment::choose($locked, $first));
    }

    public function done(FinishPlacingRequest $request, Game $game): RedirectResponse
    {
        /** @var array<int|string, array{x: float|int|string, y: float|int|string}> $positions */
        $positions = $request->validated('positions');

        return $this->act($game, true, fn (Game $locked) => GameDeployment::done($locked, $positions), $positions);
    }

    public function skip(SkipDeploymentRequest $request, Game $game): RedirectResponse
    {
        return $this->act($game, false, function (Game $locked) {
            $mine = $locked->players->filter(fn (GamePlayer $p) => $this->controls($p, $locked))->pluck('slot')->map(fn ($s) => (int) $s)->values()->all();

            return GameDeployment::skip($locked, $mine);
        });
    }

    /**
     * @param  Closure(Game): array<string, mixed>  $action
     * @param  array<int|string, array<string, mixed>>  $positions  what to tell the other player about where models were put
     */
    private function act(Game $game, bool $needsActor, Closure $action, array $positions = []): RedirectResponse
    {
        abort_unless(TabletopAccess::allows(auth()->user()), 404);

        $error = null;
        $advanced = false;

        DB::transaction(function () use ($game, $needsActor, $action, &$error, &$advanced) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $locked->load('players.crewMembers');

            // the early check is a courtesy; this one, under the lock, is the one that counts
            if ($locked->status !== GameStatusEnum::Deployment) {
                $error = 'Deployment is not under way.';

                return;
            }

            if ($needsActor) {
                $slot = GameDeployment::actorSlot($locked, GameDeployment::state($locked));
                $actor = $slot === null ? null : $locked->players->firstWhere('slot', $slot);
                abort_unless($actor instanceof GamePlayer && $this->controls($actor, $locked), 403, 'It is not your turn to do that.');
            }

            try {
                // deployment finishing is what moves the game to its next setup step
                $advanced = ($action($locked)['phase'] ?? null) === GameDeployment::DONE;
            } catch (DeploymentRefused $e) {
                $error = $e->getMessage();
            }
        });

        if ($error !== null) {
            return redirect()->back()->withMessage($error, null, MessageTypeEnum::error);
        }

        $fresh = $game->fresh(['players.crewMembers']);
        SafeBroadcast::send(function () use ($fresh, $positions, $advanced) {
            broadcast(new GameDeployChanged($fresh, GameDeployment::summary($fresh), $this->placed($positions)))->toOthers();
            if ($advanced) {
                broadcast(new GameStatusChanged($fresh))->toOthers();
            }
        });

        return redirect()->back();
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $positions
     * @return array<string, array{x: float, y: float}>
     */
    private function placed(array $positions): array
    {
        $out = [];
        foreach ($positions as $id => $at) {
            $out[(string) $id] = ['x' => round((float) $at['x'], 2), 'y' => round((float) $at['y'], 2)];
        }

        return $out;
    }

    /** You act for your own side; in a solo game you play both. */
    private function controls(GamePlayer $player, Game $game): bool
    {
        return $game->is_solo ? true : $player->user_id === auth()->id();
    }
}
