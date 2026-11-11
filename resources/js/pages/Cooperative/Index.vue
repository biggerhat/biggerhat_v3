<script setup lang="ts">
import CoopClassCard from '@/components/Cooperative/CoopClassCard.vue';
import { ROLE_LABELS, type CoopClassData, type CoopPhase, type CoopRole } from '@/components/Cooperative/types';
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { Swords } from 'lucide-vue-next';
import { computed } from 'vue';

interface EncounterRow {
    id: number;
    number: number;
    name: string;
    slug: string;
    encounter_size: number | null;
    max_hand_size: number | null;
    board_size: string | null;
    phases: CoopPhase[] | null;
}

const props = defineProps<{
    encounters: EncounterRow[];
    classes: Partial<Record<CoopRole, CoopClassData[]>>;
    roles: { name: string; value: string }[];
}>();

const rolesWithClasses = computed(() => props.roles.filter((r) => props.classes[r.value as CoopRole]?.length));
</script>

<template>
    <Head title="Cooperative Mode" />

    <PageBanner title="Cooperative Mode" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                1–3 players against a non-player crew. Encounters and enemy class cards from the Index of the Untold.
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto space-y-8 px-4 pb-10">
        <section aria-labelledby="encounters-heading" class="space-y-3">
            <h2 id="encounters-heading" class="text-lg font-semibold">Encounters</h2>
            <EmptyState
                v-if="!encounters.length"
                :icon="Swords"
                title="No encounters yet"
                description="Encounters will appear here once they are added."
            />
            <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link v-for="e in encounters" :key="e.id" :href="route('cooperative.encounter', e.slug)">
                    <Card class="h-full transition-colors hover:bg-accent/40">
                        <CardContent class="space-y-2 p-4">
                            <div class="text-xs text-muted-foreground">{{ e.number === 0 ? 'Introduction' : `Encounter ${e.number}` }}</div>
                            <div class="font-semibold">{{ e.name }}</div>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-if="e.encounter_size" variant="outline">{{ e.encounter_size }}ss</Badge>
                                <Badge v-if="e.max_hand_size" variant="outline">Hand {{ e.max_hand_size }}</Badge>
                                <Badge v-if="e.board_size" variant="outline">{{ e.board_size }}</Badge>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </section>

        <section v-for="role in rolesWithClasses" :key="role.value" :aria-labelledby="`role-${role.value}`" class="space-y-3">
            <h2 :id="`role-${role.value}`" class="text-lg font-semibold">{{ ROLE_LABELS[role.value as CoopRole] }} classes</h2>
            <div class="grid gap-3 lg:grid-cols-2">
                <CoopClassCard v-for="c in classes[role.value as CoopRole]" :key="c.id" :item="c" />
            </div>
        </section>
    </div>
</template>
