/**
 * Shared HTTP helpers for the Game Tracker's many play/setup actions. Centralizes
 * CSRF header handling and the fetch → parse boilerplate that was repeated across
 * ~30 call sites in Games/Show.vue. Each caller keeps its own post-response logic
 * (router.reload, toasts) — this only owns the request.
 */

export function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export function csrfHeaders(): Record<string, string> {
    // Prefer the XSRF-TOKEN cookie (stays in sync with the session across partial
    // reloads) over the meta tag.
    const cookie = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    if (cookie) return { 'X-XSRF-TOKEN': decodeURIComponent(cookie.split('=')[1]) };
    return { 'X-CSRF-TOKEN': csrfToken() };
}

export interface GameApiResult<T = Record<string, unknown>> {
    ok: boolean;
    status: number;
    data: T;
}

export function useGameApi() {
    const request = async <T = Record<string, unknown>>(url: string, method: string, body?: unknown): Promise<GameApiResult<T>> => {
        const opts: RequestInit = { method, headers: { 'Content-Type': 'application/json', ...csrfHeaders() } };
        if (body !== undefined) opts.body = JSON.stringify(body);
        const res = await fetch(url, opts);
        const data = (await res.json().catch(() => ({}))) as T;
        return { ok: res.ok, status: res.status, data };
    };

    return {
        csrfHeaders,
        csrfToken,
        post: <T = Record<string, unknown>>(url: string, body?: unknown) => request<T>(url, 'POST', body),
        patch: <T = Record<string, unknown>>(url: string, body?: unknown) => request<T>(url, 'PATCH', body),
        put: <T = Record<string, unknown>>(url: string, body?: unknown) => request<T>(url, 'PUT', body),
    };
}
