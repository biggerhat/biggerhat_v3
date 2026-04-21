import type { Updater } from '@tanstack/vue-table';
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import type { Ref } from 'vue';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function valueUpdater<T extends Updater<any>>(updaterOrValue: T, ref: Ref) {
    ref.value = typeof updaterOrValue === 'function' ? updaterOrValue(ref.value) : updaterOrValue;
}

/** Read the CSRF token from the Blade-rendered <meta> tag. */
export function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Set a client-readable cookie. Used for app preferences we want the server
 * to read on the next request (appearance, theme, cookie_consent) — cheap
 * SSR no-flicker state without a round-trip to the backend.
 */
export function setCookie(name: string, value: string, days = 365): void {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
}

/** Remove a previously-set cookie. */
export function clearCookie(name: string): void {
    if (typeof document === 'undefined') {
        return;
    }

    document.cookie = `${name}=;path=/;max-age=0;SameSite=Lax`;
}

/**
 * Format a **date-only** value (Laravel `date` cast) without timezone drift.
 *
 * Laravel serializes `date` casts as `2026-04-21T00:00:00.000000Z` — UTC
 * midnight. `new Date(...)` on that string parses as UTC, then
 * `toLocaleDateString` converts to the user's local zone. Anywhere west of
 * UTC, UTC midnight on the 21st is still the 20th locally → wrong day
 * displayed.
 *
 * This helper yanks the `YYYY-MM-DD` prefix and constructs a local-time
 * Date, so the displayed day matches what was saved regardless of the
 * viewer's timezone. Use this for calendar-day values (tournament event
 * dates, transmission release dates, etc.). Do NOT use it for true
 * timestamps — those should flow through `datetime` casts and be formatted
 * with `toLocaleString`.
 */
export function formatDateOnly(
    value: string | null | undefined,
    options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' },
    locale: string = 'en-US',
): string {
    if (!value) return '';
    const datePart = value.split('T')[0];
    const parts = datePart.split('-').map(Number);
    if (parts.length !== 3 || parts.some((n) => Number.isNaN(n))) {
        // Fall through to the raw Date path for anything that doesn't look
        // like YYYY-MM-DD — safer than returning an empty string.
        return new Date(value).toLocaleDateString(locale, options);
    }
    const [y, m, d] = parts;
    return new Date(y, m - 1, d).toLocaleDateString(locale, options);
}
