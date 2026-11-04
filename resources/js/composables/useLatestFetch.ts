/**
 * Search-as-you-type fetch helper: a response only counts if no newer request with the same key has started since, and
 * a failed request (rate limit, offline) resolves to null rather than throwing an unhandled rejection from a timer.
 */
export function useLatestFetch() {
    const latest: Record<string, number> = {};

    return async function fetchLatest<T>(key: string, url: string): Promise<T | null> {
        const id = (latest[key] = (latest[key] ?? 0) + 1);
        try {
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) return null;
            const data = await res.json();
            return id === latest[key] ? data : null;
        } catch {
            return null;
        }
    };
}
