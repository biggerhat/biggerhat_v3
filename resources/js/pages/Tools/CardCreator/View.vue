<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';

defineProps<{
    character: {
        id: number;
        name: string;
        title: string | null;
        display_name: string;
        faction: string;
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
        front_image: string | null;
        back_image: string | null;
        actions: any[] | null;
        abilities: any[] | null;
        keywords: any[] | null;
        characteristics: string[] | null;
    };
    creator_name: string;
}>();

const stationLabel = (station: string | null) => {
    if (!station || station === 'none') return null;
    const map: Record<string, string> = { master: 'Master', henchman: 'Henchman', enforcer: 'Enforcer', minion: 'Minion', peon: 'Peon' };
    return map[station] ?? station;
};
</script>

<template>
    <Head :title="`${character.display_name} — Custom Character`" />

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
        </PageBanner>

        <div class="container mx-auto mt-6 max-w-3xl px-4 lg:px-6">
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Card preview -->
                <Card class="overflow-hidden">
                    <div class="flex aspect-[550/950] items-center justify-center bg-muted p-4 text-center">
                        <img v-if="character.front_image" :src="'/storage/' + character.front_image" :alt="character.display_name" class="h-full w-full object-contain" />
                        <div v-else class="text-xs text-muted-foreground">
                            <FactionLogo v-if="character.faction" :faction="character.faction" class-name="mx-auto mb-2 size-16 opacity-30" />
                            <div class="font-medium">{{ character.display_name }}</div>
                            <div class="mt-1">Card preview not yet generated</div>
                        </div>
                    </div>
                </Card>

                <!-- Stats & content -->
                <div class="space-y-4">
                    <Card>
                        <CardContent class="space-y-3 p-4">
                            <div class="flex items-center gap-2">
                                <FactionLogo v-if="character.faction" :faction="character.faction" class-name="size-5" />
                                <span class="font-semibold">{{ character.display_name }}</span>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                <span v-if="stationLabel(character.station)">{{ stationLabel(character.station) }} | </span>{{ character.cost ?? '—' }}ss
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
                                    <Badge class="text-[8px]">{{ action.type }}</Badge>
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
