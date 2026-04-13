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

export function useTournamentStatus() {
    return {
        statusColor: (status: string): string => COLORS[status] ?? '',
        statusLabel: (status: string): string => LABELS[status] ?? status,
        publicStatusLabel: (status: string): string => PUBLIC_LABELS[status] ?? status,
    };
}
