import { ref } from 'vue';

export interface RecentCharacter {
    slug: string;
    name: string;
    faction: string | null;
    miniatureId: number;
    miniatureSlug: string;
}

const STORAGE_KEY = 'bh_recent_characters';
const MAX = 6;

const read = (): RecentCharacter[] => {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed.slice(0, MAX) : [];
    } catch {
        return [];
    }
};

/**
 * Per-browser "recently viewed characters" list. Storage can be blocked or empty (private windows,
 * SSR), so every access is guarded and the list simply stays empty.
 */
export function useRecentlyViewed() {
    const items = ref<RecentCharacter[]>([]);

    const load = () => {
        if (typeof window === 'undefined') return;
        items.value = read();
    };

    const record = (entry: RecentCharacter) => {
        if (typeof window === 'undefined') return;
        try {
            const next = [entry, ...read().filter((e) => e.slug !== entry.slug)].slice(0, MAX);
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
        } catch {
            // storage unavailable — nothing to do
        }
    };

    return { items, load, record };
}
