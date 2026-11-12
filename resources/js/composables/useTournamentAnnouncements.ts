import { isScoredResult, roundName } from '@/types/tournament';
import { ref, watch } from 'vue';

/**
 * Says what changed on a live tournament page for screen-reader users: results and round changes arrive through a
 * silent reload, so nothing else tells them. Feed the returned `message` into an aria-live region
 * (`TournamentLiveRegion`). Only changes between two renders of the same page are announced, never the first state.
 */
interface WatchedRound {
    id: number;
    round_number: number;
    label?: string;
    is_cut?: boolean;
    status: string;
    games: { id: number; table_number: number | null; is_bye: boolean; result: string }[];
}

interface Snapshot {
    name: string;
    cut: boolean;
    status: string;
    paired: boolean;
    scored: Set<number>;
    tables: Map<number, number | null>;
}

const snapshot = (rounds: WatchedRound[]): Map<number, Snapshot> =>
    new Map(
        rounds.map((r) => [
            r.id,
            {
                name: roundName(r),
                cut: !!r.is_cut,
                status: r.status,
                paired: r.games.length > 0,
                scored: new Set(r.games.filter((g) => !g.is_bye && isScoredResult(g.result)).map((g) => g.id)),
                tables: new Map(r.games.map((g) => [g.id, g.table_number])),
            },
        ]),
    );

/** Sentences describing what is new in `next` compared with `prev` (exported for testing). */
export function describeChanges(prev: Map<number, Snapshot>, next: Map<number, Snapshot>, tournamentStatus: { was: string; now: string }): string[] {
    const out: string[] = [];
    const be = (s: Snapshot) => (s.cut ? 'are' : 'is');

    for (const [id, now] of next) {
        const before = prev.get(id);
        if (!before) {
            if (now.paired) out.push(`${now.name} ${be(now)} paired.`);
            continue;
        }
        if (!before.paired && now.paired) out.push(`${now.name} ${be(now)} paired.`);
        if (before.status !== now.status) {
            if (now.status === 'in_progress') out.push(`${now.name} ${now.cut ? 'have' : 'has'} started.`);
            if (now.status === 'completed') out.push(`${now.name} ${be(now)} finished.`);
        }
        const newlyScored = [...now.scored].filter((g) => !before.scored.has(g));
        if (newlyScored.length === 1) {
            const table = now.tables.get(newlyScored[0]);
            out.push(table ? `Result reported for table ${table}.` : 'A result was reported.');
        } else if (newlyScored.length > 1) {
            out.push(`${newlyScored.length} results reported.`);
        }
    }
    if (tournamentStatus.was !== 'completed' && tournamentStatus.now === 'completed')
        out.push('The tournament is finished. Final results are available.');

    return out;
}

export function useTournamentAnnouncements(getRounds: () => WatchedRound[], getStatus: () => string) {
    const message = ref('');
    let previous = snapshot(getRounds());
    let previousStatus = getStatus();

    watch(
        () => [getRounds(), getStatus()] as const,
        ([rounds, status]) => {
            const next = snapshot(rounds);
            const said = describeChanges(previous, next, { was: previousStatus, now: status });
            previous = next;
            previousStatus = status;
            if (said.length) {
                // Alternate a trailing space so an identical sentence still re-triggers the live region.
                message.value = said.join(' ') + (message.value.endsWith(' ') ? '' : ' ');
            }
        },
        { deep: true },
    );

    return { message };
}
