/**
 * Subscribes to the current user's private notification channel
 * (App.Models.User.{id} — the same channel Laravel's own Notification
 * broadcasting defaults to, already authorized in routes/channels.php).
 * Mirrors useGameChannel.ts's subscribe/listen/cleanup shape.
 */
import { useToast } from '@/composables/useToast';
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

    const onNotification = (payload: NotificationPayload) => {
        unreadCount.value += 1;
        toast.info(payload.message);
    };

    const joinChannel = () => {
        if (!window.Echo || !userId) return;

        channel = window.Echo.private(`App.Models.User.${userId}`);
        EVENT_NAMES.forEach((event) => channel.listen(event, onNotification));
    };

    const leaveChannel = () => {
        if (channel && userId) {
            window.Echo?.leave(`App.Models.User.${userId}`);
            channel = null;
        }
    };

    onMounted(joinChannel);
    onUnmounted(leaveChannel);
}
