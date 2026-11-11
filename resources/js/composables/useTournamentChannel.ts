import { loadEcho } from '@/lib/echo';
import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

/** How often to refresh while the websocket is down (a venue's wifi drops, Reverb isn't configured, ...). */
const FALLBACK_POLL_MS = 30_000;

/**
 * Subscribe to the public broadcast channel for a tournament. Whenever the
 * server emits a `TournamentUpdated` event, we trigger a debounced reload.
 *
 * Pass a custom `onUpdate` to override the default full-page reload — useful
 * for the Manage page which only needs partial props refreshed.
 *
 * Returns `connected`: false until the socket is up and whenever it drops. While it is down the page
 * refreshes itself on a slow timer, and it refreshes once more when the socket comes back, so a
 * spectator screen (the display mode on a venue TV) can't quietly go stale.
 */
export function useTournamentChannel(tournamentUuid: string, onUpdate?: (event: any) => void) {
    let channel: any = null;
    let active = false;
    let reloadTimer: ReturnType<typeof setTimeout> | null = null;
    let pollTimer: ReturnType<typeof setInterval> | null = null;
    const connected = ref(false);

    const reload = (event: any) => {
        // Tournament deletion: the current page would 404 on reload — bounce
        // to the listing page instead. Skip the debounce so co-organizers
        // leave immediately.
        if (event?.action === 'tournament_deleted') {
            router.visit(route('tournaments.index'));
            return;
        }

        if (reloadTimer) clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => {
            if (onUpdate) {
                onUpdate(event);
            } else {
                router.reload({ preserveScroll: true });
            }
            reloadTimer = null;
        }, 300);
    };

    const refresh = () => (onUpdate ? onUpdate({ action: 'poll' }) : router.reload({ preserveScroll: true }));

    const startPolling = () => {
        if (pollTimer || typeof document === 'undefined') return;
        pollTimer = setInterval(() => {
            if (!connected.value && !document.hidden) refresh();
        }, FALLBACK_POLL_MS);
    };

    const watchConnection = (echo: any) => {
        const connection = echo.connector?.pusher?.connection;
        if (!connection) return;
        const set = (up: boolean) => {
            const recovered = up && !connected.value;
            connected.value = up;
            // Events fired while we were away are gone: catch up once the socket is back.
            if (recovered && channel) refresh();
        };
        connection.bind('connected', () => set(true));
        for (const state of ['disconnected', 'unavailable', 'failed', 'connecting']) connection.bind(state, () => set(false));
        connected.value = connection.state === 'connected';
    };

    const joinChannel = async () => {
        if (!tournamentUuid) return;

        active = true;
        startPolling();
        const echo = await loadEcho();
        // Unmounted while the Echo chunk was loading.
        if (!echo || !active) return;

        if (import.meta.env.DEV) console.log(`[TournamentChannel] Joining: tournament.${tournamentUuid}`);
        channel = echo.channel(`tournament.${tournamentUuid}`);
        watchConnection(echo);

        channel.listen('.TournamentUpdated', (e: any) => {
            if (import.meta.env.DEV) console.log('[TournamentChannel] Event:', e);
            reload(e);
        });
    };

    const leaveChannel = () => {
        active = false;
        if (reloadTimer) clearTimeout(reloadTimer);
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = null;
        if (channel) {
            window.Echo?.leave(`tournament.${tournamentUuid}`);
            channel = null;
        }
    };

    onMounted(() => joinChannel());
    onUnmounted(() => leaveChannel());

    return { connected };
}
