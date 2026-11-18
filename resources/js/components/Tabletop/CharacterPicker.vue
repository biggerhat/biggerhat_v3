<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Search, User } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';

export interface CharacterOption {
    id: number;
    name: string;
    faction: string;
    base_mm: number;
    sz: number;
    sp: number;
    health: number;
    faction_key?: string;
    df?: number | null;
    wp?: number | null;
    front_image?: string | null;
    back_image?: string | null;
}

const props = defineProps<{ url: string }>();
const emit = defineEmits<{ add: [character: CharacterOption] }>();

const query = ref('');
const results = ref<CharacterOption[]>([]);
const loading = ref(false);
const failed = ref(false);
const searched = ref('');

let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

async function search(text: string) {
    controller?.abort();
    controller = new AbortController();
    loading.value = true;
    failed.value = false;
    try {
        const res = await fetch(`${props.url}?q=${encodeURIComponent(text)}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!res.ok) throw new Error(String(res.status));
        results.value = (await res.json()).characters ?? [];
        searched.value = text;
    } catch (e) {
        if ((e as Error).name !== 'AbortError') failed.value = true;
    } finally {
        loading.value = false;
    }
}

// Wait for a pause in typing before asking the server.
watch(query, (text) => {
    if (timer) clearTimeout(timer);
    const trimmed = text.trim();
    if (trimmed.length < 2) {
        controller?.abort();
        results.value = [];
        searched.value = '';
        loading.value = false;
        return;
    }
    timer = setTimeout(() => search(trimmed), 250);
});
onUnmounted(() => {
    if (timer) clearTimeout(timer);
    controller?.abort();
});
</script>

<template>
    <div class="space-y-2 text-sm" data-testid="character-picker">
        <label class="relative block">
            <span class="sr-only">Search characters</span>
            <Search class="pointer-events-none absolute left-2 top-2.5 size-4 text-muted-foreground" />
            <input
                v-model="query"
                type="search"
                autocomplete="off"
                placeholder="Search by name, e.g. Pandora"
                class="w-full rounded-md border bg-background py-1.5 pl-8 pr-2"
                data-testid="character-search"
            />
        </label>
        <ul v-if="results.length" class="max-h-72 divide-y overflow-y-auto rounded-md border" data-testid="character-results">
            <li v-for="c in results" :key="c.id" class="flex items-center gap-2 px-2 py-1.5" data-testid="character-result">
                <User class="size-4 shrink-0 text-muted-foreground" />
                <span class="min-w-0 flex-1">
                    <span class="block truncate">{{ c.name }}</span>
                    <span class="block text-xs text-muted-foreground" data-testid="character-stats"
                        >{{ c.faction }} · {{ c.base_mm }} mm · Sz {{ c.sz }} · Sp {{ c.sp }} · Wd {{ c.health }}</span
                    >
                </span>
                <Button size="sm" variant="outline" :aria-label="`Add ${c.name}`" data-testid="character-add" @click="emit('add', c)">Add</Button>
            </li>
        </ul>
        <p v-else-if="failed" class="text-red-600 dark:text-red-400">The search did not work. Try again in a moment.</p>
        <p v-else-if="loading" class="text-muted-foreground">Searching...</p>
        <p v-else-if="searched" class="text-muted-foreground">No character matches "{{ searched }}".</p>
        <p v-else class="text-muted-foreground">Type at least two letters.</p>
    </div>
</template>
