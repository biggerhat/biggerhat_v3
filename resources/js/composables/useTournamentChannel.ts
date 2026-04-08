import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

export function useTournamentChannel(tournamentUuid: string) {
    let channel: any = null;
    let reloadTimer: ReturnType<typeof setTimeout> | null = null;

    const reload = () => {
        if (reloadTimer) clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => {
            router.reload({ preserveScroll: true });
            reloadTimer = null;
        }, 300);
    };

    const joinChannel = () => {
        if (!window.Echo || !tournamentUuid) return;

        if (import.meta.env.DEV) console.log(`[TournamentChannel] Joining: tournament.${tournamentUuid}`);
        channel = window.Echo.channel(`tournament.${tournamentUuid}`);

        channel.listen('.TournamentUpdated', (e: any) => {
            if (import.meta.env.DEV) console.log('[TournamentChannel] Event:', e);
            reload();
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
