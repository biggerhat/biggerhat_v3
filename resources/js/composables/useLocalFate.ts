import { createLocalFate, type FateFeed, type FateResult, type FateState } from '@/lib/fate/fate';
import { ref } from 'vue';

/**
 * The sandbox's practice deck: the same 54 cards and rules as a game's, shuffled in the browser. There is no opponent and no game to
 * keep a hand from, so every hand is shown. It lasts until the page closes or the table is reset.
 */
export function useLocalFate(): FateFeed & { reset: () => void; start: () => Promise<void> } {
    const engine = createLocalFate([1, 2]);
    const state = ref<FateState | null>(engine.state());
    const busy = ref(false);
    const error = ref<string | null>(null);

    async function act(name: string, body: Record<string, unknown> = {}): Promise<FateResult> {
        const result = engine.act(name, body);
        error.value = result.error ?? null;
        state.value = result.state ?? engine.state();
        return result;
    }

    return {
        state,
        busy,
        error,
        act,
        reset: () => {
            engine.reset();
            error.value = null;
            state.value = engine.state();
        },
        start: async () => {},
    };
}
