import { loadEcho } from '@/lib/echo';
import type { FateFeed, FateLogLine, FateResult, FateState } from '@/lib/fate/fate';
import { onUnmounted, ref } from 'vue';
import { csrfHeaders } from './useGameApi';

/** Without a live socket (or for an observer, who is not on the players' channel) look again this often while the tab is showing. */
const POLL_MS = 8000;
/** Several pings that arrive together become one request. */
const REFRESH_DEBOUNCE_MS = 250;

/**
 * A game's Fate deck: the state this viewer may see (their own hand, the public piles, only counts for the other hand) and the actions
 * that change it. Everything goes through the server (games.fate.*); the deck never exists in the browser. Nothing is fetched until
 * start(), so a board whose Fate section is shut costs nothing.
 *
 * The channel carries only a "something changed" ping with no cards (GameFateChanged); each person then asks for their own view.
 */
export function useFateGame(uuid: string, observer = false): FateFeed & { start: () => Promise<void>; stop: () => void } {
    const state = ref<FateState | null>(null);
    const busy = ref(false);
    const error = ref<string | null>(null);
    let started = false;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let poll: ReturnType<typeof setInterval> | null = null;
    let channel: { listen: (e: string, cb: () => void) => unknown; stopListening: (e: string) => unknown } | null = null;

    async function refresh() {
        try {
            const res = await fetch(route('games.fate.show', uuid), { headers: { Accept: 'application/json' } });
            if (res.ok) state.value = ((await res.json()) as { state: FateState }).state;
        } catch {
            // the next ping or poll tries again
        }
    }
    const refreshSoon = () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(refresh, REFRESH_DEBOUNCE_MS);
    };

    async function act(name: string, body: Record<string, unknown> = {}): Promise<FateResult> {
        busy.value = true;
        error.value = null;
        try {
            // answering or putting away a request names the request in the URL
            const url =
                name === 'respond' || name === 'dismiss'
                    ? route(`games.fate.prompts.${name}`, { game: uuid, prompt: Number(body.prompt) })
                    : route(`games.fate.${name}`, uuid);
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeaders() },
                body: JSON.stringify(body),
            });
            const data = (await res.json().catch(() => ({}))) as FateResult & { message?: string };
            if (data.state) state.value = data.state;
            if (!res.ok) {
                error.value = data.error ?? data.message ?? 'That did not work.';
                return { ...data, error: error.value };
            }
            return data;
        } finally {
            busy.value = false;
        }
    }

    /** The history as readable lines (the server writes them from a log that never holds a hidden card). */
    async function log(): Promise<FateLogLine[]> {
        try {
            const res = await fetch(route('games.fate.log', uuid), { headers: { Accept: 'application/json' } });
            return res.ok ? ((await res.json()) as { log: FateLogLine[] }).log : [];
        } catch {
            return [];
        }
    }

    async function start() {
        if (started) return;
        started = true;
        await refresh();
        const echo = await loadEcho();
        if (echo) {
            const ch = (observer ? echo.channel(`game-observe.${uuid}`) : echo.join(`game.${uuid}`)) as unknown as NonNullable<typeof channel>;
            ch.listen('.GameFateChanged', refreshSoon);
            channel = ch;
        }
        // observers are not on the players' channel and a missing socket gives no pings: keep looking while the tab is showing
        if (observer || !echo) poll = setInterval(() => document.visibilityState === 'visible' && refresh(), POLL_MS);
    }
    function stop() {
        if (timer) clearTimeout(timer);
        if (poll) clearInterval(poll);
        // only our listener: the play screen and the board listen on the same channel for other things
        channel?.stopListening('.GameFateChanged');
        channel = null;
        started = false;
    }
    onUnmounted(stop);

    return { state, busy, error, act, log, start, stop };
}
