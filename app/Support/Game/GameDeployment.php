<?php

namespace App\Support\Game;

use App\Enums\DeploymentEnum;
use App\Enums\GameRoleEnum;
use App\Enums\GameStatusEnum;
use App\Models\Game;
use App\Models\GamePlayer;

/**
 * Putting the crews on the table, in the order the rulebook gives (Comprehensive Rules Guide, Encounters, step I):
 *
 *  1. the defender divides their crew into two groups, either of which may be empty (`split`);
 *  2. the attacker chooses which group is deployed first (`choose`);
 *  3. the defender deploys that group (`first`);
 *  4. the attacker deploys their whole crew (`attacker`);
 *  5. the defender deploys the other group (`second`).
 *
 * A phase with nothing to place is skipped, so a defender who keeps their crew together (or has no models) goes straight on. The state is
 * kept in `games.board_state['deploy']`; every method works on a game the caller has locked (`lockForUpdate` inside a transaction) and
 * re-reads the state from it. Whether a model stands completely inside its zone is checked on the board (the zone geometry lives there):
 * the server checks who may act, in which phase, and which models.
 */
final class GameDeployment
{
    public const SPLIT = 'split';

    public const CHOOSE = 'choose';

    public const FIRST = 'first';

    public const ATTACKER = 'attacker';

    public const SECOND = 'second';

    public const DONE = 'done';

    /**
     * @return array{phase: string, later: list<int>, first: string|null, skipped: list<int>}
     */
    public static function state(Game $game): array
    {
        $game->loadMissing('players.crewMembers');
        $saved = $game->board_state['deploy'] ?? [];

        return self::settle($game, [
            'phase' => is_string($saved['phase'] ?? null) ? $saved['phase'] : self::SPLIT,
            'later' => array_values(array_map('intval', (array) ($saved['later'] ?? []))),
            'first' => in_array($saved['first'] ?? null, ['now', 'later'], true) ? $saved['first'] : null,
            'skipped' => array_values(array_map('intval', (array) ($saved['skipped'] ?? []))),
        ]);
    }

    /** The slot of the player who has to act now, or null when deployment is over (or roles are not given yet). */
    public static function actorSlot(Game $game, array $state): ?int
    {
        $role = match ($state['phase']) {
            self::SPLIT, self::FIRST, self::SECOND => GameRoleEnum::Defender,
            self::CHOOSE, self::ATTACKER => GameRoleEnum::Attacker,
            default => null,
        };

        return $role === null ? null : self::player($game, $role)?->slot;
    }

    /**
     * The crew members to place in the current phase.
     *
     * @return list<int>
     */
    public static function placing(Game $game, array $state): array
    {
        return match ($state['phase']) {
            self::FIRST => self::group($game, $state, $state['first'] ?? 'now'),
            self::ATTACKER => self::crewIds($game, GameRoleEnum::Attacker),
            self::SECOND => self::group($game, $state, ($state['first'] ?? 'now') === 'now' ? 'later' : 'now'),
            default => [],
        };
    }

    /**
     * The defender divides their crew: `$laterIds` are the models they will deploy second.
     *
     * @param  list<int>  $laterIds
     * @return array<string, mixed>
     */
    public static function split(Game $locked, array $laterIds): array
    {
        $state = self::state($locked);
        self::expect($state, self::SPLIT, 'The crew has already been divided.');

        $all = self::crewIds($locked, GameRoleEnum::Defender);
        $later = array_values(array_unique(array_map('intval', $laterIds)));
        if (array_diff($later, $all) !== []) {
            throw new DeploymentRefused('Choose models from the defender\'s crew.');
        }

        $state['later'] = $later;
        // one group empty: nothing to choose between, the other goes first
        if ($later === [] || count($later) === count($all)) {
            $state['first'] = $later === [] ? 'now' : 'later';
            $state['phase'] = self::FIRST;
        } else {
            $state['phase'] = self::CHOOSE;
        }

        return self::save($locked, self::settle($locked, $state));
    }

    /**
     * The attacker chooses which of the defender's two groups is deployed first.
     *
     * @return array<string, mixed>
     */
    public static function choose(Game $locked, string $first): array
    {
        $state = self::state($locked);
        self::expect($state, self::CHOOSE, 'There is no group to choose now.');

        if (! in_array($first, ['now', 'later'], true)) {
            throw new DeploymentRefused('Choose one of the two groups.');
        }

        $state['first'] = $first;
        $state['phase'] = self::FIRST;

        return self::save($locked, self::settle($locked, $state));
    }

    /**
     * The player who is placing says they are finished, with where each model stands. The ids must be exactly the models of this phase.
     * Moves to the next phase, and past the last one starts the next setup step.
     *
     * @param  array<int|string, array{x: float|int|string, y: float|int|string}>  $positions
     * @return array<string, mixed>
     */
    public static function done(Game $locked, array $positions): array
    {
        $state = self::state($locked);
        if (! in_array($state['phase'], [self::FIRST, self::ATTACKER, self::SECOND], true)) {
            throw new DeploymentRefused('Nothing is being placed now.');
        }

        $placing = self::placing($locked, $state);
        $given = array_map('intval', array_keys($positions));
        sort($given);
        $want = $placing;
        sort($want);
        if ($given !== $want) {
            throw new DeploymentRefused('Place every model of this group before you finish.');
        }

        $board = $locked->board_state ?? [];
        foreach ($positions as $id => $at) {
            $board['positions'][(string) $id] = ['x' => round((float) $at['x'], 2), 'y' => round((float) $at['y'], 2)];
        }
        $locked->board_state = $board;

        $state['phase'] = match ($state['phase']) {
            self::FIRST => self::ATTACKER,
            self::ATTACKER => self::SECOND,
            default => self::DONE,
        };

        return self::save($locked, self::settle($locked, $state));
    }

    /**
     * "Start without deploying": the player(s) say the crews are already set up elsewhere. When every player has said so, deployment is over.
     *
     * @param  list<int>  $slots  the slots the caller speaks for (both in a solo game)
     * @return array<string, mixed>
     */
    public static function skip(Game $locked, array $slots): array
    {
        $state = self::state($locked);
        if ($state['phase'] === self::DONE) {
            return $state;
        }

        $state['skipped'] = array_values(array_unique([...$state['skipped'], ...array_map('intval', $slots)]));
        $everyone = $locked->players->pluck('slot')->map(fn ($s) => (int) $s)->all();
        if (array_diff($everyone, $state['skipped']) === []) {
            $state['phase'] = self::DONE;
        }

        return self::save($locked, $state);
    }

    public static function isDone(array $state): bool
    {
        return $state['phase'] === self::DONE;
    }

    /**
     * What the screens are told: the phase, who acts, which models are being placed, and the split.
     *
     * @return array{phase: string, actor_slot: int|null, placing: list<int>, later: list<int>, first: string|null, skipped: list<int>, defender_slot: int|null, attacker_slot: int|null, deployment: string|null}
     */
    public static function summary(Game $game): array
    {
        $state = self::state($game);

        return [
            'phase' => $state['phase'],
            'actor_slot' => self::actorSlot($game, $state),
            'placing' => self::placing($game, $state),
            'later' => $state['later'],
            'first' => $state['first'],
            'skipped' => $state['skipped'],
            'defender_slot' => self::player($game, GameRoleEnum::Defender)?->slot,
            'attacker_slot' => self::player($game, GameRoleEnum::Attacker)?->slot,
            'deployment' => $game->deployment?->value,
        ];
    }

    /**
     * Change which deployment the table uses (standard, corner, flank, wedge) while nothing has been placed: the zones are drawn on the
     * table, so the players can see them before they choose. Once the first group is being placed the zones are fixed. The orientation goes
     * back to its first position, because the new zones are laid out differently.
     *
     * @return array<string, mixed> the summary after the change
     */
    public static function setType(Game $game, DeploymentEnum $type): array
    {
        $state = self::state($game);
        if (! in_array($state['phase'], [self::SPLIT, self::CHOOSE], true)) {
            throw new DeploymentRefused('The deployment cannot be changed once a crew has been placed.');
        }

        if ($game->deployment !== $type) {
            $board = $game->board_state ?? [];
            $board['deployment_variant'] = 0;
            $game->update(['deployment' => $type->value, 'board_state' => $board]);
        }

        return self::summary($game->fresh(['players.crewMembers']));
    }

    /**
     * The models that may be moved at the moment, or null when deployment is not under way (nothing is restricted then).
     *
     * @return list<int>|null
     */
    public static function movable(Game $game): ?array
    {
        if ($game->status !== GameStatusEnum::Deployment) {
            return null;
        }

        return self::placing($game, self::state($game));
    }

    /**
     * Skip the phases that have nothing to place.
     *
     * @param  array{phase: string, later: list<int>, first: string|null, skipped: list<int>}  $state
     * @return array{phase: string, later: list<int>, first: string|null, skipped: list<int>}
     */
    private static function settle(Game $game, array $state): array
    {
        for ($i = 0; $i < 6; $i++) {
            if ($state['phase'] === self::SPLIT && self::crewIds($game, GameRoleEnum::Defender) === []) {
                $state['first'] = 'now';
                $state['phase'] = self::FIRST;
            } elseif (in_array($state['phase'], [self::FIRST, self::ATTACKER, self::SECOND], true) && self::placing($game, $state) === []) {
                $state['phase'] = match ($state['phase']) {
                    self::FIRST => self::ATTACKER,
                    self::ATTACKER => self::SECOND,
                    default => self::DONE,
                };
            } else {
                break;
            }
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function save(Game $locked, array $state): array
    {
        $board = $locked->board_state ?? [];
        $board['deploy'] = $state;
        $locked->update(['board_state' => $board]);

        if (self::isDone($state)) {
            GameSetupFlow::advance($locked, GameStatusEnum::Deployment);
        }

        return $state;
    }

    /** @param  array<string, mixed>  $state */
    private static function expect(array $state, string $phase, string $message): void
    {
        if ($state['phase'] !== $phase) {
            throw new DeploymentRefused($message);
        }
    }

    private static function player(Game $game, GameRoleEnum $role): ?GamePlayer
    {
        return $game->players->first(fn (GamePlayer $p) => $p->role === $role);
    }

    /** @return list<int> */
    private static function crewIds(Game $game, GameRoleEnum $role): array
    {
        $player = self::player($game, $role);

        return $player === null
            ? []
            : $player->crewMembers->where('is_killed', false)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /** @return list<int> */
    private static function group(Game $game, array $state, string $which): array
    {
        $all = self::crewIds($game, GameRoleEnum::Defender);
        $later = array_values(array_intersect($all, $state['later']));

        return $which === 'later' ? $later : array_values(array_diff($all, $later));
    }
}
