<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';
import GameText from '@/components/GameText.vue';
import { Card } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
import { Swords, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface TriggerAction {
    id: number;
    name: string;
    slug: string;
    type: string;
    characters?: { id: number; display_name: string; slug: string; faction: string | null; standard_miniatures?: { id: number; slug: string }[] }[];
    upgrades?: { id: number; name: string; slug: string }[];
}

interface TriggerData {
    name: string;
    suits?: string | null;
    stone_cost?: number;
    description?: string | null;
    actions_count?: number;
    actions?: TriggerAction[];
}

const props = defineProps<{
    trigger: TriggerData;
}>();

const uniqueCharacters = computed(() => {
    const map = new Map<
        number,
        { display_name: string; slug: string; faction: string | null; standard_miniatures?: { id: number; slug: string }[] }
    >();
    for (const action of props.trigger.actions ?? []) {
        for (const char of action.characters ?? []) {
            if (!map.has(char.id)) {
                map.set(char.id, char);
            }
        }
    }
    return [...map.values()];
});

const characterCount = computed(() => uniqueCharacters.value.length);
</script>

<template>
    <Card class="flex flex-col overflow-hidden">
        <!-- Header -->
        <div class="flex items-center gap-1.5 border-b bg-secondary px-3 py-2">
            <GameIcon v-if="trigger.suits" :type="trigger.suits" class-name="h-4 inline-block shrink-0" />
            <GameIcon v-for="n in trigger.stone_cost ?? 0" :key="n" type="soulstone" class-name="h-4 inline-block shrink-0" />
            <span class="flex-1 font-semibold">{{ trigger.name }}</span>
        </div>

        <!-- Description -->
        <div v-if="trigger.description" class="px-3 py-2">
            <p class="text-xs leading-relaxed text-muted-foreground">
                <GameText :text="trigger.description" icon-class="h-4 inline-block align-text-bottom" />
            </p>
        </div>

        <!-- Footer -->
        <div class="mt-auto flex items-center justify-between border-t px-3 py-1.5 text-xs">
            <div class="flex items-center gap-1.5">
                <Users class="h-3 w-3 shrink-0 text-muted-foreground" />
                <slot name="footer">
                    <template v-if="characterCount === 1">
                        <Link
                            :href="
                                route('characters.view', {
                                    character: uniqueCharacters[0].slug,
                                    miniature: uniqueCharacters[0].standard_miniatures?.[0]?.id,
                                    slug: uniqueCharacters[0].standard_miniatures?.[0]?.slug ?? 'view',
                                })
                            "
                            class="text-primary hover:underline"
                        >
                            {{ uniqueCharacters[0].display_name }}
                        </Link>
                    </template>
                    <template v-else-if="characterCount > 1">
                        <Link :href="route('search.view', { trigger: trigger.name })" class="text-primary hover:underline">
                            {{ characterCount }} characters
                        </Link>
                    </template>
                    <span v-else class="text-muted-foreground">0 characters</span>
                </slot>
            </div>
            <Link
                v-if="(trigger.actions_count ?? trigger.actions?.length ?? 0) > 0"
                :href="route('actions.index', { trigger: trigger.name })"
                class="inline-flex items-center gap-1 text-muted-foreground transition-colors hover:text-primary"
            >
                <Swords class="h-3 w-3 shrink-0" />
                <template v-if="(trigger.actions_count ?? trigger.actions?.length ?? 0) === 1 && trigger.actions?.length === 1">
                    {{ trigger.actions[0].name }}
                </template>
                <template v-else> {{ trigger.actions_count ?? trigger.actions?.length ?? 0 }} Actions </template>
            </Link>
        </div>
    </Card>
</template>
