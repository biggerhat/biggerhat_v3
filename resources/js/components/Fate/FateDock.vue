<script setup lang="ts">
import FatePanel from '@/components/Fate/FatePanel.vue';
import { Button } from '@/components/ui/button';
import type { FateFeed } from '@/lib/fate/fate';
import { useMediaQuery } from '@vueuse/core';
import { ChevronDown, Layers, X } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Where the Fate mat lives. A visible "Fate deck" button:
 * - on a wide screen it is a full-width row in the side column and the mat opens under it;
 * - on a phone it floats at the bottom right, wherever you are on the page, and the mat opens as a bottom sheet over the lower part of
 *   the screen. The sheet is not modal: the table above stays in view and can still be touched.
 * Nothing is fetched (a game's deck) or built until it is first opened, so a table that never uses it pays nothing on a phone.
 * `floating: false` keeps the button in the page flow on a phone too (the play screen has its own floating bars).
 */
const props = withDefaults(
    defineProps<{
        feed: FateFeed & { start?: () => Promise<void> };
        names?: Record<number, string>;
        readOnly?: boolean;
        title?: string;
        floating?: boolean;
    }>(),
    { floating: true },
);

const wide = useMediaQuery('(min-width: 1024px)');
const open = ref(false);
const started = ref(false);
function toggle() {
    open.value = !open.value;
    if (open.value && !started.value) {
        started.value = true;
        void props.feed.start?.();
    }
}
</script>

<template>
    <div data-testid="fate-section">
        <!-- the button: a row in the column, or (on a phone) a floating button that goes away while the sheet is up -->
        <Button
            v-show="!(open && !wide)"
            type="button"
            :variant="open ? 'default' : 'outline'"
            :aria-expanded="open"
            :class="
                floating
                    ? 'max-lg:fixed max-lg:bottom-20 max-lg:right-3 max-lg:z-30 max-lg:rounded-full max-lg:shadow-lg lg:w-full lg:justify-between'
                    : 'w-full justify-between'
            "
            data-testid="fate-open"
            @click="toggle"
        >
            <span class="flex items-center gap-2"><Layers class="size-4" />{{ title ?? 'Fate deck' }}</span>
            <ChevronDown class="size-4 transition-transform" :class="[open ? 'rotate-180' : '', floating ? 'max-lg:hidden' : '']" />
        </Button>

        <!-- wide: docked under the button -->
        <div v-if="open && (wide || !floating)" class="mt-2 rounded-md border p-3" data-testid="fate-docked">
            <FatePanel :feed="feed" :names="names" :read-only="readOnly" />
        </div>

        <!-- phone: a bottom sheet over the lower part of the screen, above the app's tab bar; the table above stays usable -->
        <Teleport to="body">
            <div
                v-if="open && !wide && floating"
                class="fixed inset-x-0 bottom-14 z-40 flex max-h-[62vh] flex-col rounded-t-xl border border-b-0 bg-background shadow-[0_-8px_24px_rgba(0,0,0,0.25)]"
                role="region"
                aria-label="Fate deck"
                data-testid="fate-sheet"
            >
                <div class="flex items-center justify-between gap-2 border-b px-3 py-2">
                    <span class="flex items-center gap-2 font-semibold"><Layers class="size-4" />{{ title ?? 'Fate deck' }}</span>
                    <Button size="sm" variant="ghost" aria-label="Close the Fate deck" data-testid="fate-close" @click="toggle"
                        ><X class="size-4"
                    /></Button>
                </div>
                <div class="overflow-y-auto overscroll-contain p-3">
                    <FatePanel :feed="feed" :names="names" :read-only="readOnly" />
                </div>
            </div>
        </Teleport>
    </div>
</template>
