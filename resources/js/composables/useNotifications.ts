/**
 * Subscribes to the current user's private notification channel
 * (App.Models.User.{id} — the same channel Laravel's own Notification
 * broadcasting defaults to, already authorized in routes/channels.php).
 * Mirrors useGameChannel.ts's subscribe/listen/cleanup shape.
 */
import { useToast } from '@/composables/useToast';
import { loadEcho } from '@/lib/echo';
import { onMounted, onUnmounted, ref } from 'vue';

export interface NotificationPayload {
    type: string;
    message: string;
    actor: { id: number; name: string } | null;
    action_url: string | null;
}

const EVENT_NAMES = ['.friend.request.received', '.friend.request.accepted', '.campaign.invitation.received'];

export function useNotifications(userId: number | null, unreadCount: ReturnType<typeof ref<number>>) {
    const toast = useToast();
    let channel: any = null;
    let active = false;

    const onNotification = (payload: NotificationPayload) => {
        unreadCount.value += 1;
        toast.info(payload.message);
    };

    const joinChannel = async () => {
        if (!userId) return;

        active = true;
        const echo = await loadEcho();
        // Unmounted while the Echo chunk was loading.
        if (!echo || !active) return;

        channel = echo.private(`App.Models.User.${userId}`);
        EVENT_NAMES.forEach((event) => channel.listen(event, onNotification));
    };

    const leaveChannel = () => {
        active = false;
        if (channel && userId) {
            window.Echo?.leave(`App.Models.User.${userId}`);
            channel = null;
        }
    };

    onMounted(joinChannel);
    onUnmounted(leaveChannel);
}
