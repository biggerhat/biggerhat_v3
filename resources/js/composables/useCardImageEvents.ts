import { loadEcho } from '@/lib/echo';
import { onMounted, onUnmounted } from 'vue';

export interface CardImageEvent {
    kind: 'custom_character' | 'crew_card';
    id: number;
    generated_at: string | null;
}

const EVENT = '.card.image.generated';

/**
 * Calls `handler` when a saved card render finishes (CardImageGenerated, on the user's private channel), so a page can
 * reload instead of polling. `ready` resolves true once subscribed and false when realtime isn't available (no Reverb,
 * logged out) — callers fall back to polling then.
 *
 * Shares the channel with useNotifications, so it only removes its own listener and never leaves the channel.
 */
export function useCardImageEvents(userId: number | null | undefined, handler: (event: CardImageEvent) => void) {
    let channel: any = null;
    let active = false;
    let resolveReady: (value: boolean) => void = () => {};
    const ready = new Promise<boolean>((resolve) => (resolveReady = resolve));

    onMounted(async () => {
        if (!userId) return resolveReady(false);

        active = true;
        const echo = await loadEcho();
        if (!echo || !active) return resolveReady(false);

        channel = echo.private(`App.Models.User.${userId}`);
        channel.listen(EVENT, handler);
        resolveReady(true);
    });

    onUnmounted(() => {
        active = false;
        channel?.stopListening(EVENT, handler);
        channel = null;
    });

    return { ready };
}
