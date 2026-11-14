import type { TournamentGame, TournamentRound } from '@/types/tournament';

/**
 * Display helpers for tournament status. Used by both the public View page
 * and the Manage page so we have one source of truth for status copy/colors.
 */
const COLORS: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    registration: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    completed: 'bg-muted text-muted-foreground',
};

const LABELS: Record<string, string> = {
    draft: 'Draft',
    registration: 'Registration',
    active: 'Active',
    completed: 'Completed',
};

const PUBLIC_LABELS: Record<string, string> = {
    ...LABELS,
    registration: 'Registration Open',
    active: 'In Progress',
};

/** Semantic tones shared by round badges and per-game chips (badge = filled, chip = outlined). */
export type StatusTone = 'success' | 'warning' | 'info' | 'danger' | 'muted';

const BADGE_TONES: Record<StatusTone, string> = {
    success: 'border-0 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    warning: 'border-0 bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    info: 'border-0 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    danger: 'border-0 bg-destructive/15 text-destructive',
    muted: 'border-0 bg-muted text-muted-foreground',
};

const CHIP_TONES: Record<StatusTone, string> = {
    success: 'border-emerald-500/50 text-emerald-700 dark:text-emerald-300',
    warning: 'border-amber-500/60 text-amber-700 dark:text-amber-300',
    info: 'border-sky-500/50 text-sky-700 dark:text-sky-300',
    danger: 'border-destructive/50 text-destructive',
    muted: 'text-muted-foreground',
};

export interface StatusDescriptor {
    label: string;
    tone: StatusTone;
}

/** Where is this round? Setup splits into "Setup" (no pairings yet) and "Paired" (ready to start). */
export function roundStatus(round: Pick<TournamentRound, 'status' | 'games'>): StatusDescriptor {
    if (round.status === 'completed') return { label: 'Completed', tone: 'muted' };
    if (round.status === 'in_progress') return { label: 'In Progress', tone: 'success' };
    return round.games.length ? { label: 'Paired', tone: 'warning' } : { label: 'Setup', tone: 'info' };
}

/** Where is this match? Tracker progress, reported result, or waiting. */
export function gameStatus(game: TournamentGame, round: Pick<TournamentRound, 'status'>): StatusDescriptor {
    if (game.is_bye) return { label: 'Bye', tone: 'muted' };
    if (game.is_forfeit) return { label: 'Forfeit', tone: 'danger' };
    if (game.report_status === 'disputed') return { label: 'Disputed', tone: 'danger' };
    if (game.report_status === 'pending') return { label: 'Reported · awaiting confirmation', tone: 'warning' };
    if (game.result === 'agreed') return { label: 'Agreed result', tone: 'info' };
    if (game.result === 'completed') return { label: 'Reported', tone: 'success' };
    if (game.tracker_game?.status === 'in_progress') {
        return { label: `Playing · T${game.tracker_game.current_turn ?? 1}/${game.tracker_game.max_turns ?? 5}`, tone: 'warning' };
    }
    if (game.tracker_game?.status === 'abandoned') return { label: 'Tracker abandoned', tone: 'danger' };
    if (round.status === 'in_progress') return { label: 'Waiting for score', tone: 'muted' };
    return { label: 'Not started', tone: 'muted' };
}

export function useTournamentStatus() {
    return {
        badgeTone: (tone: StatusTone): string => BADGE_TONES[tone],
        chipTone: (tone: StatusTone): string => CHIP_TONES[tone],
        roundStatus,
        gameStatus,
        statusColor: (status: string): string => COLORS[status] ?? '',
        statusLabel: (status: string): string => LABELS[status] ?? status,
        publicStatusLabel: (status: string): string => PUBLIC_LABELS[status] ?? status,
    };
}
