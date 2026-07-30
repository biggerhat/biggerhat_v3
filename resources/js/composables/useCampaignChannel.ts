import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

interface PresenceMember {
    id: number;
    name: string;
}

// Mirrors useGameChannel.ts's debounce constant — merges multiple rapid
// broadcast events (e.g. several crews updating at once) into one reload.
const BROADCAST_RELOAD_DEBOUNCE_MS = 150;

/**
 * Real-time updates for a Campaign hub / Weekly Hire page (T3-34) — mirrors
 * useGameChannel.ts's presence-channel + debounced-reload pattern. Every
 * campaign member (organizer or joined player) sees crew changes, campaign
 * start, and week advances without a manual refresh.
 */
export function useCampaignChannel(campaignId: number | null, only: string[]) {
    const onlineMembers = ref<PresenceMember[]>([]);
    let channel: any = null;
    let reloadTimer: ReturnType<typeof setTimeout> | null = null;
    let pendingOnly: Set<string> = new Set();

    const reload = (keys: string[]) => {
        keys.forEach((k) => pendingOnly.add(k));
        if (reloadTimer) clearTimeout(reloadTimer);
        reloadTimer = setTimeout(() => {
            router.reload({ only: [...pendingOnly], preserveScroll: true });
            pendingOnly = new Set();
            reloadTimer = null;
        }, BROADCAST_RELOAD_DEBOUNCE_MS);
    };

    const addListeners = (ch: any) => {
        ch.listen('.CampaignCrewUpdated', (e: any) => {
            if (import.meta.env.DEV) console.log('[CampaignChannel] Event: CampaignCrewUpdated', e);
            reload(only);
        })
            .listen('.CampaignStarted', (e: any) => {
                if (import.meta.env.DEV) console.log('[CampaignChannel] Event: CampaignStarted', e);
                reload(only);
            })
            .listen('.CampaignWeekAdvanced', (e: any) => {
                if (import.meta.env.DEV) console.log('[CampaignChannel] Event: CampaignWeekAdvanced', e);
                reload(only);
            });

        if (ch.subscription) {
            ch.subscription.bind('pusher:subscription_error', (err: any) => {
                if (import.meta.env.DEV) console.error('[CampaignChannel] Subscription error:', err);
            });
        }
    };

    const joinChannel = () => {
        if (!window.Echo || !campaignId) {
            if (import.meta.env.DEV) console.warn('[CampaignChannel] Echo unavailable or no campaign id, skipping');
            return;
        }

        channel = window.Echo.join(`campaign.${campaignId}`)
            .here((members: PresenceMember[]) => {
                onlineMembers.value = members;
            })
            .joining((member: PresenceMember) => {
                onlineMembers.value.push(member);
            })
            .leaving((member: PresenceMember) => {
                onlineMembers.value = onlineMembers.value.filter((m) => m.id !== member.id);
            });
        addListeners(channel);
    };

    const leaveChannel = () => {
        if (reloadTimer) clearTimeout(reloadTimer);
        if (channel && campaignId) {
            window.Echo?.leave(`campaign.${campaignId}`);
            channel = null;
        }
    };

    onMounted(() => {
        joinChannel();
    });

    onUnmounted(() => {
        leaveChannel();
    });

    return {
        onlineMembers,
    };
}
