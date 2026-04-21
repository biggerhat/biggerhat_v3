import { factionBackground } from '@/composables/useFactionColor';
import { useToast } from '@/composables/useToast';
import { csrfToken } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { computed, ref, type ComputedRef } from 'vue';

interface Player {
    id: number;
    display_name: string;
    faction: string | null;
    is_disqualified?: boolean;
    dropped_after_round?: number | null;
}

interface Tournament {
    uuid: string;
    players: Player[];
    [key: string]: unknown;
}

/**
 * Per-page state and helpers shared by the Tournament Manage page and any
 * tab components broken out of it.
 *
 * Keeps a single error timer + showError() so subcomponents can report
 * failures consistently. Memoizes the player ID → player lookup.
 */
export function useTournament<T extends Tournament>(tournament: ComputedRef<T> | { value: T }) {
    const toast = useToast();
    const submitting = ref(false);

    /**
     * Surface a user-facing action error. Now renders via the global toast
     * system so it's visible regardless of which tab/dialog is open. The
     * exported `actionError` ref is kept for backward compat with any page
     * still rendering an inline banner, but is no longer the primary channel.
     */
    const actionError = ref<string | null>(null);
    const showError = (msg: string) => {
        actionError.value = msg;
        toast.error(msg);
    };

    /**
     * Internal: the shared fetch + error-extract pipeline used by doAction and
     * doModalAction. Returns { ok, error } where error is a user-facing string
     * on failure. Callers decide whether to surface the error globally.
     */
    const runAction = async (url: string, method: string, body?: Record<string, unknown>): Promise<{ ok: boolean; error: string | null }> => {
        try {
            // Include the Echo socket id so `broadcast(...)->toOthers()` can
            // exclude this client from its own TournamentUpdated event. Without
            // it, every mutation bounces back through useTournamentChannel as
            // a router.reload — the "phantom auto-refresh" users see after
            // saving scenarios, scoring, pairing, etc.
            const echo = (window as unknown as { Echo?: { socketId?: () => string | undefined } }).Echo;
            const socketId = echo?.socketId?.();
            const headers: Record<string, string> = {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                Accept: 'application/json',
            };
            if (socketId) headers['X-Socket-Id'] = socketId;

            const opts: RequestInit = { method, headers };
            if (body) opts.body = JSON.stringify(body);
            const res = await fetch(url, opts);
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                return { ok: false, error: err.error ?? err.message ?? 'Action failed.' };
            }
            return { ok: true, error: null };
        } catch {
            return { ok: false, error: 'Network error. Please try again.' };
        }
    };

    /**
     * Fetch wrapper — auto-attaches CSRF + JSON headers, surfaces server-side
     * `error` payloads via showError() (the page-level banner). Returns true
     * on 2xx, false otherwise.
     *
     * Use this for page-level actions. For actions fired from inside a dialog,
     * prefer doModalAction() so the error shows INSIDE the dialog instead of
     * on the (disabled) page behind it.
     */
    const doAction = async (url: string, method: string = 'POST', body?: Record<string, unknown>): Promise<boolean> => {
        const { ok, error } = await runAction(url, method, body);
        if (!ok && error) showError(error);
        return ok;
    };

    /**
     * Modal-friendly variant of doAction — returns { ok, error } and does NOT
     * fire the page-level toast. The caller is responsible for displaying the
     * error inside the open dialog. Without this, validation errors appear on
     * the disabled page behind the modal where the user can't see them.
     */
    const doModalAction = async (
        url: string,
        method: string = 'POST',
        body?: Record<string, unknown>,
    ): Promise<{ ok: boolean; error: string | null }> => {
        return runAction(url, method, body);
    };

    /**
     * Refresh the page's `tournament` and `standings` Inertia props without
     * destroying scroll/local component state. Pages that need broader
     * refreshes should call router.reload directly.
     */
    const reloadProps = (only: string[] = ['tournament', 'standings']) => {
        router.reload({ only, preserveScroll: true, preserveState: true });
    };

    /** Memoized player ID → player map for O(1) lookups in heavy renders. */
    const playerMap = computed(() => {
        const map = new Map<number, Player>();
        for (const p of tournament.value.players) {
            map.set(p.id, p);
        }
        return map;
    });

    const playerName = (id: number | null | undefined): string => {
        if (!id) return 'BYE';
        return playerMap.value.get(id)?.display_name ?? 'Unknown';
    };

    const playerFaction = (id: number | null | undefined): string | null => {
        if (!id) return null;
        return playerMap.value.get(id)?.faction ?? null;
    };

    return {
        submitting,
        actionError,
        showError,
        doAction,
        doModalAction,
        reloadProps,
        playerMap,
        playerName,
        playerFaction,
        factionBackground,
    };
}
