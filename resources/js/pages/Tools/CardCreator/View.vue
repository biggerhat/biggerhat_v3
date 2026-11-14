<script setup lang="ts">
import CardRenderer from '@/components/CardCreator/CardRenderer.vue';
import CopyToMyCards from '@/components/CardCreator/CopyToMyCards.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import SeoHead from '@/components/SeoHead.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useCardImageEvents } from '@/composables/useCardImageEvents';
import { cacheBustedImagePath } from '@/lib/cacheBustedImage';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Download, Loader2 } from 'lucide-vue-next';

const props = defineProps<{
    share_code: string;
    card_image_pending?: boolean;
    card_art_path?: string | null;
    character: {
        id: number;
        name: string;
        title: string | null;
        display_name: string;
        card_art_x?: number;
        card_art_y?: number;
        card_art_zoom?: number;
        card_art_dx?: number;
        card_art_dy?: number;
        combination_image?: string | null;
        front_image?: string | null;
        card_image_generated_at?: string | null;
        faction: string;
        second_faction: string | null;
        station: string;
        cost: number | null;
        health: number;
        defense: number;
        defense_suit: string | null;
        willpower: number;
        willpower_suit: string | null;
        speed: number;
        size: number | null;
        base: string;
        actions:
            | {
                  name: string;
                  type: string;
                  stat: number | null;
                  damage: string | null;
                  description: string | null;
                  triggers: { name: string; suits: string | null; description: string | null }[];
              }[]
            | null;
        abilities: { name: string; description: string | null }[] | null;
        keywords: { id: number | null; name: string }[] | null;
        characteristics: string[] | null;
        linked_crew_upgrades: { source_type: string; id: number; name: string }[] | null;
        linked_totems: { source_type: string; id: number; name: string }[] | null;
    };
    creator_name: string;
}>();

// The owner viewing their card sees the download button appear when the render finishes.
useCardImageEvents(usePage<SharedData>().props.auth.user?.id, (event) => {
    if (event.kind === 'custom_character' && event.id === props.character.id) router.reload({ only: ['character'] });
});

const stationLabel = (station: string | null) => {
    if (!station || station === 'none') return null;
    const map: Record<string, string> = { master: 'Master', enforcer: 'Enforcer', minion: 'Minion', peon: 'Peon' };
    return map[station] ?? station;
};
const signedIn = !!usePage<SharedData>().props.auth.user;
</script>

<template>
    <SeoHead
        :title="`${character.display_name} — Custom Character`"
        :description="`${character.display_name}, a custom ${stationLabel(character.station) ?? 'character'} card by ${creator_name}.`"
        :image="character.combination_image ?? character.front_image"
    />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner :title="character.display_name">
            <template #subtitle>
                <div class="flex items-center gap-2 px-2 text-sm text-muted-foreground">
                    <Badge class="bg-purple-600 text-white">Custom</Badge>
                    <span>Created by {{ creator_name }}</span>
                </div>
            </template>
            <template v-if="signedIn || character.combination_image || card_image_pending" #actions>
                <CopyToMyCards v-if="signedIn" :url="route('tools.card_creator.copy_shared', share_code)" />
                <span
                    v-if="!character.combination_image && card_image_pending"
                    class="mr-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                    role="status"
                >
                    <Loader2 class="size-4 animate-spin" /> Preparing card image…
                </span>
                <a
                    v-else-if="character.combination_image"
                    :href="'/storage/' + cacheBustedImagePath(character.combination_image, character.card_image_generated_at)"
                    :download="`${character.display_name}.png`"
                    class="mr-2 inline-flex h-9 items-center gap-1.5 rounded-md border bg-background px-3 text-sm font-medium transition-colors hover:bg-accent [@media(pointer:coarse)]:h-11"
                >
                    <Download class="size-4" /> Download card
                </a>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 max-w-3xl px-4 lg:px-6">
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Live card preview -->
                <CardRenderer
                    :name="character.name"
                    :title="character.title"
                    :faction="character.faction || 'guild'"
                    :second-faction="character.second_faction"
                    :station="character.station || 'minion'"
                    :cost="character.cost"
                    :health="character.health"
                    :defense="character.defense"
                    :defense-suit="character.defense_suit"
                    :willpower="character.willpower"
                    :willpower-suit="character.willpower_suit"
                    :speed="character.speed"
                    :size="character.size"
                    :base="String(character.base)"
                    :keywords="character.keywords ?? []"
                    :characteristics="character.characteristics ?? []"
                    :character-image="card_art_path ?? null"
                    :character-image-focus="{
                        x: character.card_art_x ?? 50,
                        y: character.card_art_y ?? 50,
                        zoom: character.card_art_zoom ?? 100,
                        dx: character.card_art_dx ?? 0,
                        dy: character.card_art_dy ?? 0,
                    }"
                    :actions="character.actions ?? []"
                    :abilities="character.abilities ?? []"
                    :linked-crew-upgrades="character.linked_crew_upgrades ?? []"
                    :linked-totems="character.linked_totems ?? []"
                />

                <!-- Stats & content -->
                <div class="space-y-4">
                    <Card>
                        <CardContent class="space-y-3 p-4">
                            <div class="flex items-center gap-2">
                                <FactionLogo v-if="character.faction" :faction="character.faction" class-name="size-5" />
                                <span class="font-semibold">{{ character.display_name }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                <span v-if="stationLabel(character.station)">{{ stationLabel(character.station) }} | </span
                                >{{ character.cost ?? '—' }}ss
                            </div>

                            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="rounded bg-muted px-2 py-1.5">
                                    <div class="font-bold">{{ character.defense }}</div>
                                    <div class="text-muted-foreground">Df</div>
                                </div>
                                <div class="rounded bg-muted px-2 py-1.5">
                                    <div class="font-bold">{{ character.willpower }}</div>
                                    <div class="text-muted-foreground">Wp</div>
                                </div>
                                <div class="rounded bg-muted px-2 py-1.5">
                                    <div class="font-bold">{{ character.speed }}</div>
                                    <div class="text-muted-foreground">Mv</div>
                                </div>
                                <div class="rounded bg-muted px-2 py-1.5">
                                    <div class="font-bold">{{ character.health }}</div>
                                    <div class="text-muted-foreground">Health</div>
                                </div>
                                <div class="rounded bg-muted px-2 py-1.5">
                                    <div class="font-bold">{{ character.size ?? '—' }}</div>
                                    <div class="text-muted-foreground">Sz</div>
                                </div>
                                <div class="rounded bg-muted px-2 py-1.5">
                                    <div class="font-bold">{{ character.base }}mm</div>
                                    <div class="text-muted-foreground">Base</div>
                                </div>
                            </div>

                            <div v-if="character.keywords?.length" class="flex flex-wrap gap-1">
                                <Badge v-for="kw in character.keywords" :key="kw.name" variant="secondary" class="text-[10px]">{{ kw.name }}</Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="character.linked_crew_upgrades?.length || character.linked_totems?.length">
                        <CardContent class="space-y-2 p-4">
                            <div v-if="character.linked_crew_upgrades?.length">
                                <h3 class="text-xs font-semibold">Crew Upgrades</h3>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <Badge
                                        v-for="u in character.linked_crew_upgrades"
                                        :key="u.source_type + '-' + u.id"
                                        variant="secondary"
                                        class="text-[10px]"
                                        >{{ u.name }}</Badge
                                    >
                                </div>
                            </div>
                            <div v-if="character.linked_totems?.length">
                                <h3 class="text-xs font-semibold">Totems</h3>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <Badge
                                        v-for="t in character.linked_totems"
                                        :key="t.source_type + '-' + t.id"
                                        variant="secondary"
                                        class="text-[10px]"
                                        >{{ t.name }}</Badge
                                    >
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="character.abilities?.length">
                        <CardContent class="space-y-2 p-4">
                            <h3 class="text-xs font-semibold">Abilities</h3>
                            <div v-for="ability in character.abilities" :key="ability.name" class="rounded border p-2 text-xs">
                                <div class="font-medium">{{ ability.name }}</div>
                                <div v-if="ability.description" class="mt-0.5 text-muted-foreground">{{ ability.description }}</div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card v-if="character.actions?.length">
                        <CardContent class="space-y-2 p-4">
                            <h3 class="text-xs font-semibold">Actions</h3>
                            <div v-for="action in character.actions" :key="action.name" class="rounded border p-2 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-medium">{{ action.name }}</span>
                                    <Badge class="text-[10px]">{{ action.type }}</Badge>
                                    <span v-if="action.stat" class="text-muted-foreground">Stat {{ action.stat }}</span>
                                    <span v-if="action.damage" class="text-muted-foreground">| {{ action.damage }}</span>
                                </div>
                                <div v-if="action.description" class="mt-0.5 text-muted-foreground">{{ action.description }}</div>
                                <div v-if="action.triggers?.length" class="mt-1 space-y-0.5 border-t pt-1">
                                    <div v-for="trigger in action.triggers" :key="trigger.name" class="text-[10px]">
                                        <span class="font-medium">{{ trigger.suits }} {{ trigger.name }}:</span>
                                        <span class="text-muted-foreground"> {{ trigger.description }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>
</template>
