<script setup lang="ts">
/**
 * Admin flavour of the Cmd-K palette: jump to any admin page the user can
 * access (same source as the sidebar) and, once something is typed, look up
 * records by name. Must render inside a `<CommandDialog>` so `useCommand()`
 * can read the search text.
 */
import { CommandGroup, CommandItem, CommandSeparator, useCommand } from '@/components/ui/command';
import { adminNavIcon } from '@/lib/adminNavIcons';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import axios from 'axios';
import { Loader2 } from 'lucide-vue-next';
import { computed, markRaw, ref } from 'vue';

interface RecordResult {
    type: string;
    label: string;
    url: string;
}

const emit = defineEmits<{
    (e: 'select', url: string, external: boolean): void;
}>();

const page = usePage<SharedData>();
const { filterState } = useCommand();

const pages = computed(() =>
    (page.props.adminNav ?? []).flatMap((group) =>
        group.items.map((item) => markRaw({ ...item, group: group.title, icon: adminNavIcon(item.icon) })),
    ),
);

const term = computed(() => (filterState.search ?? '').trim());
const records = ref<RecordResult[]>([]);
const searching = ref(false);

// Drop out-of-order responses: only the latest request may write results.
let requestId = 0;

watchDebounced(
    term,
    async (value) => {
        const id = ++requestId;
        if (value.length < 2) {
            records.value = [];
            searching.value = false;
            return;
        }
        searching.value = true;
        try {
            const response = await axios.get(route('admin.search'), { params: { q: value } });
            if (id === requestId) records.value = response.data?.results ?? [];
        } catch {
            if (id === requestId) records.value = [];
        } finally {
            if (id === requestId) searching.value = false;
        }
    },
    { debounce: 200 },
);

// Records are filtered server-side, but cmdk also fuzzy-filters by item value;
// embedding the term keeps matches that hit a column other than the label
// (e.g. a user's email) from being hidden.
const recordValue = (record: RecordResult) => `record:${term.value}:${record.type}:${record.label}`;
</script>

<template>
    <CommandGroup heading="Admin pages">
        <CommandItem
            v-for="item in pages"
            :key="item.key"
            :value="`page:${item.title}:${item.group}`"
            @select="emit('select', item.href, item.external)"
        >
            <component :is="item.icon" class="mr-2 size-4 text-muted-foreground" />
            {{ item.title }}
            <span class="ml-auto text-xs text-muted-foreground">{{ item.group }}</span>
        </CommandItem>
    </CommandGroup>

    <template v-if="term.length >= 2">
        <CommandSeparator />
        <CommandGroup heading="Records">
            <CommandItem
                v-for="record in records"
                :key="record.url + record.type"
                :value="recordValue(record)"
                @select="emit('select', record.url, false)"
            >
                {{ record.label }}
                <span class="ml-auto text-xs text-muted-foreground">{{ record.type }}</span>
            </CommandItem>
            <div v-if="searching" class="flex items-center gap-2 px-2 py-2 text-xs text-muted-foreground">
                <Loader2 class="size-3.5 animate-spin" /> Searching records…
            </div>
            <div v-else-if="records.length === 0" class="px-2 py-2 text-xs text-muted-foreground">No matching records.</div>
        </CommandGroup>
    </template>
</template>
