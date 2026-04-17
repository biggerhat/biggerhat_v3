import { clearCookie, setCookie } from '@/lib/utils';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

export type ConsentChoice = 'accepted' | 'declined';

const COOKIE_NAME = 'cookie_consent';
const STORAGE_KEY = 'cookie_consent';

const getStoredConsent = (): ConsentChoice | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const value = localStorage.getItem(STORAGE_KEY);
    return value === 'accepted' || value === 'declined' ? value : null;
};

/**
 * Cookie-consent state management. Same cookie + localStorage hybrid as
 * `useAppearance` so the SSR-rendered Blade template can gate Google
 * Analytics on first byte (no network traffic until accepted).
 *
 * Accepting triggers a page reload so the server re-renders with the GA
 * script. Declining is silent — GA was never loaded anyway.
 */
export function useCookieConsent() {
    const consent = ref<ConsentChoice | null>(null);

    onMounted(() => {
        consent.value = getStoredConsent();
    });

    const hasDecided = computed(() => consent.value !== null);

    const acceptConsent = () => {
        consent.value = 'accepted';
        localStorage.setItem(STORAGE_KEY, 'accepted');
        setCookie(COOKIE_NAME, 'accepted');

        // Reload so Blade re-renders with the Google Analytics script
        // injected. preserveScroll avoids jumping to top on a decision the
        // user just made at the bottom of the viewport.
        router.reload({ preserveScroll: true });
    };

    const declineConsent = () => {
        consent.value = 'declined';
        localStorage.setItem(STORAGE_KEY, 'declined');
        setCookie(COOKIE_NAME, 'declined');
        // No reload — GA was never loaded, nothing on the page changes.
    };

    /**
     * Clear the user's stored choice. Primarily useful for a "change
     * preferences" link (Tier 2); kept here so the banner re-shows on next
     * mount. Not currently wired to any UI.
     */
    const resetConsent = () => {
        consent.value = null;
        localStorage.removeItem(STORAGE_KEY);
        clearCookie(COOKIE_NAME);
    };

    return {
        consent,
        hasDecided,
        acceptConsent,
        declineConsent,
        resetConsent,
    };
}
