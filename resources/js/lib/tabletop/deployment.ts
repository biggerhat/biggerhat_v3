/**
 * Deployment on the tabletop, in the words on screen. The state comes from the server (App\Support\Game\GameDeployment); this turns it into
 * what to tell each player, so the board's panel and the play screen's card say the same thing.
 */

export type DeployPhase = 'split' | 'choose' | 'first' | 'attacker' | 'second' | 'done';

export interface DeployState {
    phase: DeployPhase;
    /** The slot of the player who has to act now (null when it is over). */
    actor_slot: number | null;
    /** The crew members being placed in this phase. */
    placing: number[];
    /** The defender's models held back to deploy second. */
    later: number[];
    /** Which of the defender's groups goes first: the one kept for now or the one held back. */
    first: 'now' | 'later' | null;
    skipped: number[];
    defender_slot: number | null;
    attacker_slot: number | null;
}

export interface DeployPlayer {
    slot: number;
    name: string;
    mine: boolean;
}

export interface DeployWords {
    /** One line: what is happening. */
    headline: string;
    /** Whether it is the viewer's turn to act. */
    yourTurn: boolean;
}

/** What is happening and whose move it is, for a viewer who controls the given slots. */
export function deployWords(state: DeployState, players: DeployPlayer[]): DeployWords {
    const name = (slot: number | null) => players.find((p) => p.slot === slot)?.name ?? 'The other player';
    const mine = (slot: number | null) => players.some((p) => p.slot === slot && p.mine);
    const actor = state.actor_slot;
    const yourTurn = actor !== null && mine(actor);
    const who = yourTurn ? 'You' : name(actor);
    const verb = (you: string, other: string) => (yourTurn ? you : other);

    switch (state.phase) {
        case 'split':
            return {
                yourTurn,
                headline: `${who} ${verb('divide', 'divides')} the defender's crew into two groups. The attacker will choose which group is deployed first.`,
            };
        case 'choose':
            return { yourTurn, headline: `${who} ${verb('choose', 'chooses')} which of the defender's two groups is deployed first.` };
        case 'first':
            return { yourTurn, headline: `${who} ${verb('deploy', 'deploys')} the first group, completely inside the defender's zone.` };
        case 'attacker':
            return { yourTurn, headline: `${who} ${verb('deploy', 'deploys')} the whole crew, completely inside the attacker's zone.` };
        case 'second':
            return { yourTurn, headline: `${who} ${verb('deploy', 'deploys')} the rest of the crew, completely inside the defender's zone.` };
        default:
            return { yourTurn: false, headline: 'Deployment is finished. Next, each player secretly chooses a scheme.' };
    }
}
