<script setup lang="ts">
import CoopClassCard from '@/components/Cooperative/CoopClassCard.vue';
import { ROLE_LABELS, type CoopCampaign, type CoopClassData, type CoopPhase } from '@/components/Cooperative/types';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Eye } from 'lucide-vue-next';
import { ref } from 'vue';

interface Neighbor {
    number: number;
    name: string;
    slug: string;
}

const props = defineProps<{
    encounter: {
        number: number;
        name: string;
        narrative: string | null;
        encounter_size: number | null;
        max_hand_size: number | null;
        board_size: string | null;
        challenge_mode: string | null;
        phases: CoopPhase[] | null;
        campaign: CoopCampaign | null;
    };
    classes: Record<string, CoopClassData>;
    neighbors: { previous: Neighbor | null; next: Neighbor | null };
}>();

// Later phases hide text the rulebook says not to read until instructed.
const revealed = ref<Set<number>>(new Set([0]));
const reveal = (i: number) => revealed.value.add(i);

const sections: { key: 'setup' | 'special_rules' | 'victory' | 'defeat' | 'progression'; label: string }[] = [
    { key: 'setup', label: 'Setup' },
    { key: 'special_rules', label: 'Special rules' },
    { key: 'victory', label: 'Victory' },
    { key: 'defeat', label: 'Defeat' },
    { key: 'progression', label: 'Progression' },
];

const suggested = (phase: CoopPhase) =>
    (phase.role_order ?? []).map((entry) => ({ ...entry, class: entry.class_slug ? props.classes[entry.class_slug] : undefined }));
</script>

<template>
    <Head :title="`${encounter.number === 0 ? 'Introduction' : `Encounter ${encounter.number}`}: ${encounter.name}`" />

    <PageBanner :title="encounter.name" class="mb-2">
        <template #subtitle>
            <div class="my-auto flex flex-wrap items-center gap-1.5 px-2 py-1 text-xs md:text-sm">
                <span class="text-muted-foreground">{{ encounter.number === 0 ? 'Introduction' : `Encounter ${encounter.number}` }}</span>
                <Badge v-if="encounter.encounter_size" variant="outline">{{ encounter.encounter_size }}ss</Badge>
                <Badge v-if="encounter.max_hand_size" variant="outline">Hand {{ encounter.max_hand_size }}</Badge>
                <Badge v-if="encounter.board_size" variant="outline">{{ encounter.board_size }}</Badge>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-4xl space-y-6 px-4 pb-10 pt-4">
        <p v-if="encounter.narrative" class="italic text-muted-foreground">{{ encounter.narrative }}</p>

        <Card v-for="(phase, i) in encounter.phases ?? []" :key="i">
            <CardContent class="space-y-4 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold">{{ phase.title }}</h2>
                    <Badge v-if="phase.max_hand_size" variant="outline">Hand {{ phase.max_hand_size }}</Badge>
                </div>

                <div v-if="i > 0 && !revealed.has(i)" class="rounded-md border border-dashed p-4 text-center">
                    <p class="mb-3 text-sm text-muted-foreground">The rulebook says not to read this phase until instructed.</p>
                    <Button variant="outline" size="sm" class="gap-1" @click="reveal(i)"><Eye class="size-4" /> Reveal this phase</Button>
                </div>

                <template v-else>
                    <p v-if="phase.narrative" class="italic text-muted-foreground">{{ phase.narrative }}</p>

                    <div v-if="phase.role_order?.length">
                        <h3 class="mb-1 text-sm font-semibold">Role activation order</h3>
                        <ol class="flex flex-wrap gap-2">
                            <li v-for="(entry, j) in suggested(phase)" :key="j" class="rounded-md border px-2 py-1 text-sm">
                                {{ j + 1 }}. {{ ROLE_LABELS[entry.role] }}
                                <a v-if="entry.class" :href="`#class-${entry.class.slug}`" class="text-muted-foreground hover:underline">
                                    ({{ entry.class.name }})
                                </a>
                            </li>
                        </ol>
                    </div>

                    <template v-for="section in sections" :key="section.key">
                        <div v-if="phase[section.key]?.length">
                            <h3 class="mb-1 text-sm font-semibold">{{ section.label }}</h3>
                            <component
                                :is="section.key === 'setup' ? 'ol' : 'ul'"
                                :class="section.key === 'setup' ? 'list-decimal' : 'list-disc'"
                                class="ml-5 space-y-1 text-sm"
                            >
                                <li v-for="(line, j) in phase[section.key]" :key="j">{{ line }}</li>
                            </component>
                        </div>
                    </template>
                </template>
            </CardContent>
        </Card>

        <Card v-if="encounter.challenge_mode">
            <CardContent class="space-y-1 p-4">
                <h2 class="text-lg font-semibold">Challenge mode</h2>
                <p class="whitespace-pre-line text-sm">{{ encounter.challenge_mode }}</p>
            </CardContent>
        </Card>

        <Card
            v-if="
                encounter.campaign &&
                (encounter.campaign.aftermath?.length || encounter.campaign.next_encounter || encounter.campaign.victory || encounter.campaign.defeat)
            "
        >
            <CardContent class="space-y-3 p-4 text-sm">
                <h2 class="text-lg font-semibold">Campaign</h2>
                <div v-if="encounter.campaign.victory"><span class="font-medium">Victory:</span> {{ encounter.campaign.victory }}</div>
                <div v-if="encounter.campaign.defeat"><span class="font-medium">Defeat:</span> {{ encounter.campaign.defeat }}</div>
                <div v-if="encounter.campaign.aftermath?.length">
                    <h3 class="mb-1 font-semibold">Aftermath</h3>
                    <ol class="ml-5 list-decimal space-y-1">
                        <li v-for="(line, j) in encounter.campaign.aftermath" :key="j">{{ line }}</li>
                    </ol>
                </div>
                <div v-if="encounter.campaign.next_encounter">
                    <span class="font-medium">Next encounter:</span> {{ encounter.campaign.next_encounter }}
                </div>
            </CardContent>
        </Card>

        <section v-if="Object.keys(classes).length" class="space-y-3">
            <h2 class="text-lg font-semibold">Suggested classes</h2>
            <div class="grid gap-3 lg:grid-cols-2">
                <CoopClassCard v-for="c in Object.values(classes)" :key="c.slug" :item="c" />
            </div>
        </section>

        <nav class="flex justify-between gap-2" aria-label="Encounter navigation">
            <Link v-if="neighbors.previous" :href="route('cooperative.encounter', neighbors.previous.slug)">
                <Button variant="outline" class="gap-1"><ChevronLeft class="size-4" /> {{ neighbors.previous.name }}</Button>
            </Link>
            <span v-else />
            <Link v-if="neighbors.next" :href="route('cooperative.encounter', neighbors.next.slug)">
                <Button variant="outline" class="gap-1">{{ neighbors.next.name }} <ChevronRight class="size-4" /></Button>
            </Link>
        </nav>
    </div>
</template>
