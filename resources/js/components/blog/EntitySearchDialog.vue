<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import axios from 'axios';
import { Loader2 } from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';

interface EntityResult {
    entityType: string;
    entityId: string | number;
    entitySlug: string;
    displayName: string;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
    }>(),
    {
        title: 'Link Entity',
        description: 'Search for a character, keyword, faction, upgrade, action, ability, scheme, strategy, deployment, token, marker, or package to insert.',
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'select', entity: EntityResult): void;
}>();

const searchQuery = ref('');
const results = ref<EntityResult[]>([]);
const loading = ref(false);
const inputRef = ref<InstanceType<typeof Input> | null>(null);
let debounceTimer: ReturnType<typeof setTimeout>;

const openModel = computed({
    get: () => props.open,
    set: (val) => emit('update:open', val),
});

watch(searchQuery, (q) => {
    clearTimeout(debounceTimer);
    if (q.length < 2) {
        results.value = [];
        loading.value = false;
        return;
    }
    loading.value = true;
    debounceTimer = setTimeout(async () => {
        try {
            const response = await axios.get(route('api.blog.entity-search'), { params: { q } });
            results.value = response.data.results;
        } catch (err) {
            console.error('Entity search failed:', err);
            results.value = [];
        } finally {
            loading.value = false;
        }
    }, 300);
});

watch(openModel, (val) => {
    if (!val) {
        searchQuery.value = '';
        results.value = [];
    } else {
        nextTick(() => {
            inputRef.value?.$el?.focus();
        });
    }
});

const groupedResults = computed(() => {
    const groups: Record<string, EntityResult[]> = {};
    for (const r of results.value) {
        if (!groups[r.entityType]) groups[r.entityType] = [];
        groups[r.entityType].push(r);
    }
    return groups;
});

const typeLabels: Record<string, string> = {
    character: 'Characters',
    keyword: 'Keywords',
    faction: 'Factions',
    upgrade: 'Upgrades',
    action: 'Actions',
    ability: 'Abilities',
    scheme: 'Schemes',
    strategy: 'Strategies',
    deployment: 'Deployments',
    token: 'Tokens',
    marker: 'Markers',
    package: 'Packages',
};

const typeBadgeClass: Record<string, string> = {
    character: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    keyword: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    faction: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    upgrade: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    action: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    ability: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
    scheme: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200',
    strategy: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    deployment: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    token: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
    marker: 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
    package: 'bg-lime-100 text-lime-800 dark:bg-lime-900 dark:text-lime-200',
};

const handleSelect = (entity: EntityResult) => {
    emit('select', entity);
};
</script>

<template>
    <Dialog v-model:open="openModel">
        <DialogContent class="max-w-md gap-0 p-0">
            <DialogHeader class="p-4 pb-2">
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>
            <div class="px-4 pb-2">
                <Input ref="inputRef" v-model="searchQuery" placeholder="Start typing to search..." autofocus />
            </div>
            <div class="max-h-[300px] overflow-y-auto px-2 pb-4">
                <div v-if="loading" class="flex items-center justify-center py-6">
                    <Loader2 class="h-5 w-5 animate-spin text-muted-foreground" />
                </div>
                <div
                    v-else-if="searchQuery.length >= 2 && Object.keys(groupedResults).length === 0"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    No results found.
                </div>
                <div v-else-if="searchQuery.length < 2 && searchQuery.length > 0" class="py-6 text-center text-sm text-muted-foreground">
                    Type at least 2 characters to search.
                </div>
                <template v-for="(items, type) in groupedResults" :key="type">
                    <div class="px-2 py-1.5 text-xs font-medium text-muted-foreground">{{ typeLabels[type as string] ?? type }}</div>
                    <button
                        v-for="entity in items"
                        :key="`${entity.entityType}-${entity.entityId}`"
                        class="flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground"
                        @click="handleSelect(entity)"
                    >
                        <Badge :class="['border-0 text-[10px]', typeBadgeClass[entity.entityType] ?? '']" variant="outline">
                            {{ entity.entityType.slice(0, 3).toUpperCase() }}
                        </Badge>
                        <span>{{ entity.displayName }}</span>
                    </button>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>
