import { clearCookie, setCookie } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
 * `useAppearance` so the SSR-rendered Blade template can seed the correct
 * Google Analytics Consent Mode default on first byte.
 *
 * The initial value is seeded synchronously from the `cookie_consent`
 * Inertia shared prop (the server already knows the choice from the HTTP
 * cookie — see HandleInertiaRequests) rather than only from onMounted() +
 * localStorage. That prop is identical during SSR and client hydration, so
 * a returning visitor's already-made choice renders correctly on the very
 * first paint instead of showing the banner for one tick and then hiding it.
 *
 * gtag is always loaded (Consent Mode v2, analytics_storage denied by
 * default); accepting/declining flips analytics_storage via
 * gtag('consent','update') in-session — no page reload required.
 */
export function useCookieConsent() {
    const page = usePage<SharedData>();
    const consent = ref<ConsentChoice | null>(page.props.cookie_consent ?? getStoredConsent());

    const hasDecided = computed(() => consent.value !== null);

    const acceptConsent = () => {
        consent.value = 'accepted';
        localStorage.setItem(STORAGE_KEY, 'accepted');
        setCookie(COOKIE_NAME, 'accepted');

        // Consent Mode: gtag is already loaded (denied by default), so upgrade
        // analytics storage on for this session immediately — no reload needed.
        if (typeof window !== 'undefined' && typeof window.gtag === 'function') {
            window.gtag('consent', 'update', { analytics_storage: 'granted' });
        }
    };

    const declineConsent = () => {
        consent.value = 'declined';
        localStorage.setItem(STORAGE_KEY, 'declined');
        setCookie(COOKIE_NAME, 'declined');

        // Keep analytics storage denied (the default). Explicit in case the
        // user is switching from a prior 'accepted' choice in the same session.
        if (typeof window !== 'undefined' && typeof window.gtag === 'function') {
            window.gtag('consent', 'update', { analytics_storage: 'denied' });
        }
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
