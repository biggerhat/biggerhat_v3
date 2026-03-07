<script setup lang="ts">
import { Combobox, ComboboxAnchor, ComboboxEmpty, ComboboxGroup, ComboboxInput, ComboboxItem, ComboboxList } from '@/components/ui/combobox';
import { TagsInput, TagsInputInput, TagsInputItem, TagsInputItemDelete, TagsInputItemText } from '@/components/ui/tags-input';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

export interface TaggedEntity {
    entityType: string;
    entitySlug: string;
    displayName: string;
}

const modelValue = defineModel<TaggedEntity[]>({ default: () => [] });

const open = ref(false);
const searchTerm = ref('');
const searchResults = ref<TaggedEntity[]>([]);
const isSearching = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const allowedTypes = ['character', 'keyword', 'upgrade', 'faction', 'scheme', 'strategy', 'token', 'marker', 'package'];

const typeMeta: Record<string, { label: string; badge: string; badgeClass: string }> = {
    character: { label: 'Characters', badge: 'CHR', badgeClass: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' },
    keyword: { label: 'Keywords', badge: 'KEY', badgeClass: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' },
    upgrade: { label: 'Upgrades', badge: 'UPG', badgeClass: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200' },
    faction: { label: 'Factions', badge: 'FAC', badgeClass: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' },
    scheme: { label: 'Schemes', badge: 'SCH', badgeClass: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' },
    strategy: { label: 'Strategies', badge: 'STR', badgeClass: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200' },
    token: { label: 'Tokens', badge: 'TKN', badgeClass: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200' },
    marker: { label: 'Markers', badge: 'MKR', badgeClass: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200' },
    package: { label: 'Packages', badge: 'PKG', badgeClass: 'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200' },
};

const entityKey = (e: TaggedEntity) => `${e.entityType}:${e.entitySlug}`;

const selectedKeys = computed(() => new Set(modelValue.value.map(entityKey)));

const groupedResults = computed(() => {
    const filtered = searchResults.value.filter((r) => allowedTypes.includes(r.entityType) && !selectedKeys.value.has(entityKey(r)));
    const groups: Record<string, TaggedEntity[]> = {};
    for (const r of filtered) {
        if (!groups[r.entityType]) groups[r.entityType] = [];
        groups[r.entityType].push(r);
    }
    return groups;
});

const hasResults = computed(() => Object.keys(groupedResults.value).length > 0);

const tagValues = computed(() => modelValue.value.map(entityKey));

const search = async (query: string) => {
    if (query.length < 2) {
        searchResults.value = [];
        return;
    }
    isSearching.value = true;
    try {
        const { data } = await axios.get('/api/blog/entity-search', { params: { q: query } });
        searchResults.value = data.results ?? [];
    } catch {
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

watch(searchTerm, (val) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    if (!val || val.length < 2) {
        searchResults.value = [];
        return;
    }
    debounceTimer = setTimeout(() => search(val), 300);
});

const handleSelect = (key: string) => {
    const result = searchResults.value.find((r) => entityKey(r) === key);
    if (result && !selectedKeys.value.has(key)) {
        modelValue.value = [...modelValue.value, result];
    }
    searchTerm.value = '';
};

const handleRemove = (key: string) => {
    modelValue.value = modelValue.value.filter((e) => entityKey(e) !== key);
};

const getEntityForKey = (key: string) => modelValue.value.find((e) => entityKey(e) === key);
</script>

<template>
    <Combobox v-model="tagValues" v-model:open="open" :ignore-filter="true" multiple>
        <ComboboxAnchor as-child>
            <TagsInput
                :model-value="tagValues"
                @update:model-value="(v: string[]) => handleRemove(tagValues.find((k) => !v.includes(k)) ?? '')"
                class="w-full gap-2 px-2"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <TagsInputItem v-for="key in tagValues" :key="key" :value="key">
                        <TagsInputItemText class="flex items-center gap-1">
                            <span
                                v-if="typeMeta[getEntityForKey(key)?.entityType ?? '']"
                                class="mr-0.5 inline-flex rounded px-1 py-0.5 text-[10px] font-bold leading-none"
                                :class="typeMeta[getEntityForKey(key)?.entityType ?? '']?.badgeClass"
                            >
                                {{ typeMeta[getEntityForKey(key)?.entityType ?? '']?.badge }}
                            </span>
                            {{ getEntityForKey(key)?.displayName }}
                        </TagsInputItemText>
                        <TagsInputItemDelete />
                    </TagsInputItem>
                </div>

                <ComboboxInput v-model="searchTerm" as-child>
                    <TagsInputInput
                        placeholder="Search entities..."
                        class="h-auto w-full min-w-[200px] border-none p-0 focus-visible:ring-0"
                        @keydown.enter.prevent
                        @focus="open = true"
                    />
                </ComboboxInput>
            </TagsInput>

            <ComboboxList class="w-[--reka-popper-anchor-width]">
                <ComboboxEmpty>
                    <span v-if="isSearching">Searching...</span>
                    <span v-else-if="searchTerm.length < 2">Type at least 2 characters to search</span>
                    <span v-else>No results found</span>
                </ComboboxEmpty>

                <template v-if="hasResults">
                    <ComboboxGroup v-for="(items, type) in groupedResults" :key="type">
                        <div class="px-2 py-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            {{ typeMeta[type]?.label ?? type }}
                        </div>
                        <ComboboxItem
                            v-for="item in items"
                            :key="entityKey(item)"
                            :value="entityKey(item)"
                            @select.prevent="
                                (ev: any) => {
                                    if (typeof ev.detail.value === 'string') {
                                        handleSelect(ev.detail.value);
                                    }
                                }
                            "
                        >
                            <span
                                class="mr-1.5 inline-flex rounded px-1 py-0.5 text-[10px] font-bold leading-none"
                                :class="typeMeta[item.entityType]?.badgeClass"
                            >
                                {{ typeMeta[item.entityType]?.badge }}
                            </span>
                            {{ item.displayName }}
                        </ComboboxItem>
                    </ComboboxGroup>
                </template>
            </ComboboxList>
        </ComboboxAnchor>
    </Combobox>
</template>
