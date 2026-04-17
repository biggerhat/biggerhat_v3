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
