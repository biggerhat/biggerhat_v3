import { isScoredResult, type TournamentRound } from '@/types/tournament';
import { computed, type ComputedRef } from 'vue';

/**
 * One obvious "what do I do now" for the tournament organizer, derived from the tournament and its latest round,
 * plus a four-stage progress strip. Actions reuse the same handlers as the buttons deeper in the Manage page.
 */
export type StepStage = 'players' | 'pairing' | 'play' | 'results';

export interface NextStep {
    stage: StepStage;
    title: string;
    detail: string;
    actionLabel?: string;
    action?: () => void;
    disabled?: boolean;
}

export const STEP_STAGES: { key: StepStage; label: string }[] = [
    { key: 'players', label: 'Players' },
    { key: 'pairing', label: 'Pairing' },
    { key: 'play', label: 'Play' },
    { key: 'results', label: 'Results' },
];

interface StepTournament {
    status: string;
    team_size?: number | null;
    players: unknown[];
    rounds: unknown[];
}

/** Everything the guidance reads from or triggers on the Manage page. */
export interface NextStepContext {
    tournament: StepTournament;
    latestRound: ComputedRef<TournamentRound | null>;
    activePlayerCount: ComputedRef<number>;
    canFinalize: ComputedRef<boolean>;
    submitting: { readonly value: boolean };
    transitionLabel: (status: string) => string | undefined;
    pairBlockReason: (round: TournamentRound) => string | null;
    scenarioBlockReason: (round: TournamentRound) => string | null;
    startBlockReason: (round: TournamentRound) => string | null;
    canEndRound: (round: TournamentRound) => boolean;
    openStatusDialog: () => void;
    openFinalizeDialog: () => void;
    createRound: () => void;
    generatePairings: (roundId: number) => void;
    startRound: (roundId: number) => void;
    endRound: (roundId: number) => void;
    editScenario: (round: TournamentRound) => void;
    showRounds: () => void;
}

export function useTournamentNextStep(ctx: () => NextStepContext) {
    const nextStep = computed<NextStep | null>(() => {
        const c = ctx();
        const t = c.tournament;
        const round = c.latestRound.value;
        const nextRoundNumber = t.rounds.length + 1;

        if (t.status === 'completed') return null;
        if (t.status === 'draft') {
            return {
                stage: 'players',
                title: 'Set up your tournament',
                detail: 'Add players or collect RSVPs, then open registration.',
                actionLabel: c.transitionLabel('draft'),
                action: c.openStatusDialog,
            };
        }
        if (t.status === 'registration') {
            const teams = (t.team_size ?? 1) > 1;
            const count = t.players.length;
            const noun = teams ? (count === 1 ? 'team is' : 'teams are') : count === 1 ? 'player is' : 'players are';
            return {
                stage: 'players',
                title: 'Registration is open',
                detail: `${count} ${noun} in. Start the tournament when everyone has arrived.`,
                actionLabel: c.transitionLabel('registration'),
                action: c.openStatusDialog,
            };
        }
        if (!round) {
            return {
                stage: 'pairing',
                title: 'Add Round 1',
                detail: 'Create the first round, set its scenario, then pair the players.',
                actionLabel: 'Add Round 1',
                action: c.createRound,
            };
        }
        if (round.status === 'setup' && round.games.length === 0) {
            const reason = c.pairBlockReason(round);
            if (reason === null) {
                return {
                    stage: 'pairing',
                    title: `Pair Round ${round.round_number}`,
                    detail: `The scenario is set. Generate Swiss pairings for ${c.activePlayerCount.value} players.`,
                    actionLabel: 'Auto-pair players',
                    action: () => c.generatePairings(round.id),
                    disabled: c.submitting.value,
                };
            }
            return {
                stage: 'pairing',
                title: `Set up Round ${round.round_number}`,
                detail: reason,
                actionLabel: 'Set scenario',
                action: () => c.editScenario(round),
            };
        }
        if (round.status === 'setup') {
            // The server refuses to start a round whose scenario is incomplete, so say so instead of offering Start.
            const scenarioReason = c.scenarioBlockReason(round);
            if (scenarioReason) {
                return {
                    stage: 'pairing',
                    title: `Set up Round ${round.round_number}`,
                    detail: scenarioReason.replace('before pairing', 'before starting'),
                    actionLabel: 'Set scenario',
                    action: () => c.editScenario(round),
                };
            }
            const startReason = c.startBlockReason(round);
            if (startReason) {
                return {
                    stage: 'pairing',
                    title: `Finish pairing Round ${round.round_number}`,
                    detail: startReason,
                    actionLabel: 'Fix pairings',
                    action: c.showRounds,
                };
            }
            return {
                stage: 'pairing',
                title: `Round ${round.round_number} is paired`,
                detail: 'Check the pairings, then start the round to create the games.',
                actionLabel: `Start Round ${round.round_number}`,
                action: () => c.startRound(round.id),
                disabled: c.submitting.value,
            };
        }
        if (round.status === 'in_progress') {
            const total = round.games.filter((g) => !g.is_bye).length;
            const done = round.games.filter((g) => !g.is_bye && isScoredResult(g.result)).length;
            if (c.canEndRound(round)) {
                return {
                    stage: 'play',
                    title: `Round ${round.round_number}: all scores are in`,
                    detail: 'Every game has a result. End the round to update standings.',
                    actionLabel: `End Round ${round.round_number}`,
                    action: () => c.endRound(round.id),
                    disabled: c.submitting.value,
                };
            }
            return {
                stage: 'play',
                title: `Round ${round.round_number} is in progress`,
                detail: `${done} of ${total} games reported. The round can end once every result is entered.`,
                actionLabel: 'View games',
                action: c.showRounds,
            };
        }
        // Latest round completed
        if (c.canFinalize.value) {
            return {
                stage: 'results',
                title: 'All rounds are finished',
                detail: 'Finalize the standings to complete the tournament.',
                actionLabel: 'Finalize standings',
                action: c.openFinalizeDialog,
            };
        }
        return {
            stage: 'pairing',
            title: `Round ${round.round_number} is complete`,
            detail: `Add Round ${nextRoundNumber} when you're ready.`,
            actionLabel: `Add Round ${nextRoundNumber}`,
            action: c.createRound,
        };
    });

    return { nextStep };
}
