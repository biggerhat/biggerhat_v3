<?php

namespace App\Services\Game;

use App\Enums\GameRoleEnum;
use App\Enums\GameStatusEnum;
use App\Exceptions\FateException;
use App\Models\Game;
use App\Models\GameCrewMember;
use App\Models\GameLog;
use App\Models\GamePlayer;
use Illuminate\Support\Facades\DB;

/**
 * Where a two-player game is in the turn (CRG, Turn sequence). The tracker already knows which models have activated; this adds who has
 * initiative, whose activation it is, and what passing allows:
 *
 * - Turn 1: the attacker has initiative. After that, the player who did NOT activate the last model chooses who has it.
 * - Activations alternate: when a model activates, the other player becomes the active player (if they still have models to activate).
 * - A player may pass with fewer models left to activate than their opponent, or tied for fewest, but not straight after the opponent passed.
 *
 * It is a guide for the table: it never blocks a model from being marked activated, it only tells people whose turn it is.
 */
class TurnSequence
{
    /**
     * @return array{turn: int, max_turns: int, initiative: int|null, active: int|null, chooser: int|null, unactivated: array<int, int>, can_pass: array<int, bool>, pass_note: string|null}|null
     */
    public function state(Game $game): ?array
    {
        if ($game->status !== GameStatusEnum::InProgress) {
            return null;
        }
        $players = $game->players()->orderBy('slot')->get(['id', 'slot', 'role']);
        if ($players->count() < 2) {
            return null;
        }
        $left = $this->unactivated($game, $players);
        $slots = $players->pluck('slot')->all();
        $turn = (int) $game->current_turn;

        $initiative = $game->initiative_slot;
        if ($initiative === null && $turn === 1) {
            // the attacker goes first on turn 1
            $attacker = $players->first(fn (GamePlayer $p) => $p->role === GameRoleEnum::Attacker);
            $initiative = $attacker !== null ? $attacker->slot : $slots[0];
        }
        $chooser = null;
        if ($initiative === null) {
            $last = $game->last_activated_slot;
            $chooser = $last !== null ? $this->other($slots, (int) $last) : $slots[1];
        }

        $active = $initiative === null ? null : ($game->active_slot ?? $initiative);
        $allDone = array_sum($left) === 0;
        if ($allDone || ($active !== null && ($left[$active] ?? 0) === 0 && ($left[$this->other($slots, $active)] ?? 0) > 0)) {
            // someone with nothing left to activate does not hold up the other (step B: skip to transfer)
            $active = $allDone ? null : $this->other($slots, (int) $active);
        }

        $canPass = [];
        $note = null;
        foreach ($slots as $slot) {
            $opponent = $this->other($slots, $slot);
            $mine = $left[$slot] ?? 0;
            $theirs = $left[$opponent] ?? 0;
            $passedBack = $game->passed_slot === $opponent;
            $canPass[$slot] = $initiative !== null && $mine > 0 && $mine <= $theirs && ! $passedBack;
            if ($slot === $active && ! $canPass[$slot]) {
                $note = $passedBack
                    ? 'Your opponent just passed, so you may not pass straight back.'
                    : ($mine > $theirs ? 'You have more models left to activate than your opponent, so you may not pass.' : null);
            }
        }

        return [
            'turn' => $turn,
            'max_turns' => (int) $game->max_turns,
            'initiative' => $initiative,
            'active' => $active,
            'chooser' => $chooser,
            'unactivated' => $left,
            'can_pass' => $canPass,
            'pass_note' => $note,
        ];
    }

    /** A model was marked activated by this slot: the other player is now the active player (when they still have models to activate). */
    public function activated(Game $game, int $slot): void
    {
        DB::transaction(function () use ($game, $slot) {
            $locked = Game::query()->lockForUpdate()->find($game->id);
            if ($locked === null || $locked->status !== GameStatusEnum::InProgress) {
                return;
            }
            $players = $locked->players()->orderBy('slot')->get(['id', 'slot', 'role']);
            $slots = $players->pluck('slot')->all();
            $left = $this->unactivated($locked, $players);
            $other = $this->other($slots, $slot);
            $next = ($left[$other] ?? 0) > 0 ? $other : (($left[$slot] ?? 0) > 0 ? $slot : null);
            $locked->update(['last_activated_slot' => $slot, 'passed_slot' => null, 'active_slot' => $next]);
        });
    }

    /** The active player passes: it goes to the other player. Only when the rules allow it. */
    public function pass(Game $game, GamePlayer $player): void
    {
        DB::transaction(function () use ($game, $player) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $state = $this->state($locked);
            if ($state === null || $state['initiative'] === null) {
                throw new FateException('Choose who has initiative first.');
            }
            if ($state['active'] !== $player->slot) {
                throw new FateException('It is not your activation.');
            }
            if (! ($state['can_pass'][$player->slot] ?? false)) {
                throw new FateException($state['pass_note'] ?? 'You may not pass now.');
            }
            $slots = array_keys($state['unactivated']);
            $locked->update(['passed_slot' => $player->slot, 'active_slot' => $this->other($slots, $player->slot)]);
            GameLog::query()->create(['game_id' => $locked->id, 'game_player_id' => $player->id, 'action' => 'turn.pass', 'payload' => ['turn' => $locked->current_turn], 'created_at' => now()]);
        });
    }

    /** From turn 2, the player who did not activate last chooses who has initiative this turn. */
    public function chooseInitiative(Game $game, GamePlayer $chooser, int $initiative): void
    {
        DB::transaction(function () use ($game, $chooser, $initiative) {
            $locked = Game::query()->lockForUpdate()->findOrFail($game->id);
            $state = $this->state($locked);
            if ($state === null || $state['chooser'] === null) {
                throw new FateException('Initiative is already decided this turn.');
            }
            if ($state['chooser'] !== $chooser->slot) {
                throw new FateException('Only the player who did not activate last chooses initiative.');
            }
            if (! array_key_exists($initiative, $state['unactivated'])) {
                throw new FateException('Choose one of the two players.');
            }
            $locked->update(['initiative_slot' => $initiative, 'active_slot' => $initiative, 'passed_slot' => null]);
            GameLog::query()->create(['game_id' => $locked->id, 'game_player_id' => $chooser->id, 'action' => 'turn.initiative', 'payload' => ['turn' => $locked->current_turn, 'initiative' => $initiative], 'created_at' => now()]);
        });
    }

    /** A new turn: initiative is chosen again, nobody has passed. (Who activated last is kept: it decides who chooses.) */
    public function newTurn(Game $locked): void
    {
        $locked->update(['initiative_slot' => null, 'active_slot' => null, 'passed_slot' => null]);
    }

    /**
     * Living models that have not activated, per slot.
     *
     * @param  \Illuminate\Support\Collection<int, GamePlayer>  $players
     * @return array<int, int>
     */
    private function unactivated(Game $game, $players): array
    {
        $counts = GameCrewMember::query()
            ->where('game_id', $game->id)
            ->where('is_killed', false)
            ->where('is_activated', false)
            ->selectRaw('game_player_id, count(*) as n')
            ->groupBy('game_player_id')
            ->pluck('n', 'game_player_id');

        return $players->mapWithKeys(fn (GamePlayer $p) => [$p->slot => (int) ($counts[$p->id] ?? 0)])->all();
    }

    /** @param  list<int>  $slots */
    private function other(array $slots, int $slot): int
    {
        foreach ($slots as $s) {
            if ($s !== $slot) {
                return (int) $s;
            }
        }

        return $slot;
    }
}
