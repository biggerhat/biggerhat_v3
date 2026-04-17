import { clearCookie, setCookie } from '@/lib/utils';
import { onMounted, ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

// Theme is the accent-color variant applied via `data-theme` on <html>.
// 'default' = no tint (today's neutral black/white primary). Values are the
// short slug form (matching existing Tailwind `bg-*` classes + CSS selectors)
// so the cookie value can be dropped directly into the HTML attribute
// server-side with no mapping.
export type Theme =
    | 'default'
    | 'arcanists'
    | 'bayou'
    | 'explorerssociety'
    | 'guild'
    | 'neverborn'
    | 'outcasts'
    | 'resurrectionists'
    | 'tenthunders';

export function updateTheme(value: Appearance) {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia('(prefers-color-scheme: dark)');
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle('dark', systemTheme === 'dark');
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const getStoredTheme = (): Theme | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('theme') as Theme | null;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    // Fall back to 'dark' (matches the server default in HandleAppearance).
    updateTheme(currentAppearance || 'dark');
};

export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize appearance from saved preference or default to dark.
    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'dark');

    // Set up system theme change listener (only fires when user picked 'system').
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

/**
 * Apply the saved faction accent theme as the app boots. Mirrors
 * `initializeTheme()` for appearance. Safe to call on a cold cache — absent
 * state = no-op (keeps the neutral default).
 */
export function initializeAccent() {
    if (typeof window === 'undefined') {
        return;
    }

    const saved = getStoredTheme();
    applyTheme(saved || 'default');
}

/**
 * Toggle the `data-theme` attribute on <html>. Removes it entirely for
 * 'default' so CSS overrides scoped by `[data-theme="..."]` don't apply.
 * The stored value is already the CSS slug form (e.g. `tenthunders`) so no
 * mapping is needed here.
 */
function applyTheme(value: Theme) {
    if (typeof document === 'undefined') {
        return;
    }

    if (value === 'default') {
        delete document.documentElement.dataset.theme;
        return;
    }

    document.documentElement.dataset.theme = value;
}

export function useAppearance() {
    const appearance = ref<Appearance>('dark');
    const theme = ref<Theme>('default');

    onMounted(() => {
        initializeTheme();
        initializeAccent();

        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;
        if (savedAppearance) {
            appearance.value = savedAppearance;
        }

        const savedTheme = getStoredTheme();
        if (savedTheme) {
            theme.value = savedTheme;
        }
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('appearance', value);

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    function updateAccentTheme(value: Theme) {
        theme.value = value;

        if (value === 'default') {
            // No preference = remove all traces so SSR falls back to neutral.
            localStorage.removeItem('theme');
            clearCookie('theme');
        } else {
            localStorage.setItem('theme', value);
            setCookie('theme', value);
        }

        applyTheme(value);
    }

    return {
        appearance,
        theme,
        updateAppearance,
        updateAccentTheme,
    };
}
