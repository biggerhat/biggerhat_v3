<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle, DrawerTrigger } from '@/components/ui/drawer';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    attrs: Record<string, unknown>;
}>();

const entityType = computed(() => props.attrs.entityType as string);
const entitySlug = computed(() => props.attrs.entitySlug as string);
const displayName = computed(() => props.attrs.displayName as string);

const entityData = ref<Record<string, unknown> | null>(null);
const loading = ref(false);
const loaded = ref(false);

const typeColor = computed(() => {
    const map: Record<string, string> = {
        character: 'bg-blue-100 text-blue-800 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800',
        keyword: 'bg-green-100 text-green-800 hover:bg-green-200 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800',
        faction: 'bg-purple-100 text-purple-800 hover:bg-purple-200 dark:bg-purple-900 dark:text-purple-200 dark:hover:bg-purple-800',
        upgrade: 'bg-orange-100 text-orange-800 hover:bg-orange-200 dark:bg-orange-900 dark:text-orange-200 dark:hover:bg-orange-800',
        action: 'bg-red-100 text-red-800 hover:bg-red-200 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800',
        ability: 'bg-teal-100 text-teal-800 hover:bg-teal-200 dark:bg-teal-900 dark:text-teal-200 dark:hover:bg-teal-800',
    };
    return map[entityType.value] ?? 'bg-gray-100 text-gray-800';
});

const loadEntityData = async () => {
    if (loaded.value) return;
    loading.value = true;

    try {
        const response = await axios.get(route('api.blog.entity-show', { type: entityType.value, slug: entitySlug.value }));
        entityData.value = response.data;
    } catch (err) {
        console.error('Entity load failed:', err);
    }

    loading.value = false;
    loaded.value = true;
};

const navigateToEntity = () => {
    const link = entityData.value?.link as string | null;
    if (link) {
        router.get(link);
    }
};
</script>

<template>
    <Drawer @update:open="(open: boolean) => open && loadEntityData()">
        <DrawerTrigger as-child>
            <Badge :class="['cursor-pointer border-0', typeColor]" variant="outline">
                {{ displayName }}
            </Badge>
        </DrawerTrigger>
        <DrawerContent>
            <div class="mx-auto w-full max-w-lg">
                <DrawerHeader>
                    <DrawerTitle>{{ entityData?.name ?? displayName }}</DrawerTitle>
                    <p class="text-sm capitalize text-muted-foreground">{{ entityType }}</p>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <div v-if="loading" class="flex items-center justify-center py-8">
                        <Loader2 class="h-6 w-6 animate-spin text-muted-foreground" />
                    </div>
                    <template v-else-if="entityData">
                        <!-- Character: show card images -->
                        <div v-if="entityType === 'character' && (entityData.miniatures as any[])?.length" class="flex justify-center gap-4">
                            <div v-for="miniature in (entityData.miniatures as any[])" :key="miniature.id" class="w-48">
                                <CharacterCardView
                                    :miniature="miniature"
                                    :character-slug="entitySlug"
                                    :show-link="false"
                                />
                            </div>
                        </div>

                        <!-- Non-character types: simple display -->
                        <div v-else class="text-center text-sm text-muted-foreground">
                            {{ entityData.name }}
                        </div>
                    </template>
                </div>
                <DrawerFooter class="flex-row justify-center gap-2">
                    <Button v-if="entityData?.link" @click="navigateToEntity" size="sm">View Full Details</Button>
                    <DrawerClose as-child>
                        <Button variant="outline" size="sm">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
