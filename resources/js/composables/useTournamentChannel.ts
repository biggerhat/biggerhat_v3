import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

/**
 * Subscribe to the public broadcast channel for a tournament. Whenever the
 * server emits a `TournamentUpdated` event, we trigger a debounced reload.
 *
 * Pass a custom `onUpdate` to override the default full-page reload — useful
 * for the Manage page which only needs partial props refreshed.
 */
export function useTournamentChannel(tournamentUuid: string, onUpdate?: (event: any) => void) {
    let channel: any = null;
    let reloadTimer: ReturnType<typeof setTimeout> | null = null;

    const reload = (event: any) => {
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

    const joinChannel = () => {
        if (!window.Echo || !tournamentUuid) return;

        if (import.meta.env.DEV) console.log(`[TournamentChannel] Joining: tournament.${tournamentUuid}`);
        channel = window.Echo.channel(`tournament.${tournamentUuid}`);

        channel.listen('.TournamentUpdated', (e: any) => {
            if (import.meta.env.DEV) console.log('[TournamentChannel] Event:', e);
            reload(e);
        });
    };

    const leaveChannel = () => {
        if (reloadTimer) clearTimeout(reloadTimer);
        if (channel) {
            window.Echo?.leave(`tournament.${tournamentUuid}`);
            channel = null;
        }
    };

    onMounted(() => joinChannel());
    onUnmounted(() => leaveChannel());
}
