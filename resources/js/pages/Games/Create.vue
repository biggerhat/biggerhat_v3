<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Swords, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Season {
    value: string;
    label: string;
}

interface FormatOption {
    value: string;
    label: string;
    default_encounter_size: number;
    uses_scenario: boolean;
}

const props = defineProps<{
    seasons: Season[];
    encounter_sizes: number[];
    formats: FormatOption[];
}>();

const gameName = ref('');
const encounterSize = ref(50);
const season = ref('core');
const isSolo = ref(false);
const format = ref<string>(props.formats[0]?.value ?? 'standard');
const submitting = ref(false);

const activeFormat = computed(() => props.formats.find((f) => f.value === format.value) ?? props.formats[0]);
const isBonanza = computed(() => format.value === 'bonanza_brawl');

// When the user switches format, snap encounter size to the format's default
// so the form reads honestly. Bonanza is locked server-side regardless.
// Bonanza also force-flips solo on (the tracker is a personal scratchpad for
// FFA play, not a 2-player duel). `immediate: true` covers the case where
// Bonanza happens to be the initial format (e.g. if formats[0] flips), so the
// watcher isn't relying on a user-driven change to set the flag.
watch(
    format,
    () => {
        if (activeFormat.value) {
            encounterSize.value = activeFormat.value.default_encounter_size;
        }
        if (isBonanza.value) {
            isSolo.value = true;
        }
    },
    { immediate: true },
);

const submit = () => {
    if (submitting.value) return;
    submitting.value = true;
    // Belt-and-suspenders: force is_solo=true at submit time for Bonanza so a
    // stale toggle value can't ride through (the toggle is hidden under
    // Bonanza, but Vue refs persist when v-if flips). The server enforces
    // this too, but sending the right value avoids a confusing round-trip.
    const payloadIsSolo = isBonanza.value ? true : isSolo.value;
    router.post(
        route('games.store'),
        {
            name: gameName.value || null,
            encounter_size: encounterSize.value,
            season: season.value,
            is_solo: payloadIsSolo,
            format: format.value,
        },
        { onFinish: () => (submitting.value = false) },
    );
};
</script>

<template>
    <Head title="Create Game" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Create Game" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">Set up a new encounter</div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <Button variant="ghost" class="mb-4 gap-1.5 text-sm" @click="router.get(route('games.index'))">
                <ArrowLeft class="size-4" />
                Back to Games
            </Button>

            <Card class="mx-auto max-w-lg">
                <CardContent class="space-y-6 p-6">
                    <div class="flex items-center gap-3">
                        <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                            <Swords class="size-5" />
                        </div>
                        <div>
                            <h2 class="font-semibold">New Encounter</h2>
                            <p class="text-sm text-muted-foreground">Configure the game format</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-2">
                            <Label>Game Name <span class="text-xs text-muted-foreground">(optional)</span></Label>
                            <Input v-model="gameName" placeholder="e.g. League Round 3, Casual Tuesday" />
                        </div>

                        <div class="space-y-2">
                            <Label>Format</Label>
                            <Select v-model="format">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="f in formats" :key="f.value" :value="f.value">{{ f.label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="isBonanza" class="text-xs text-muted-foreground">
                                Bonanza Brawl is single-model FFA at 11ss. The Game Tracker covers crew creation and manual VP — Loot deck, initiative
                                order, and Treasure Pile markers are tracked off-table by the Dealer.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label>
                                Encounter Size (Soulstones)
                                <span v-if="isBonanza" class="text-xs text-muted-foreground">(locked at 11 for Bonanza Brawl)</span>
                            </Label>
                            <NumberField v-model="encounterSize" :min="10" :max="100" :step="isBonanza ? 1 : 5" :disabled="isBonanza">
                                <NumberFieldContent>
                                    <NumberFieldDecrement />
                                    <NumberFieldInput />
                                    <NumberFieldIncrement />
                                </NumberFieldContent>
                            </NumberField>
                        </div>

                        <div class="space-y-2">
                            <Label>Season</Label>
                            <Select v-model="season">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="s in seasons" :key="s.value" :value="s.value">
                                        {{ s.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">Determines which strategies and schemes are available</p>
                        </div>
                    </div>

                    <div v-if="!isBonanza" class="flex items-center justify-between rounded-lg border p-3">
                        <div class="flex items-center gap-2">
                            <User class="size-4 text-muted-foreground" />
                            <div>
                                <Label class="cursor-pointer" @click="isSolo = !isSolo">Solo Mode</Label>
                                <p class="text-xs text-muted-foreground">Track both players yourself</p>
                            </div>
                        </div>
                        <Switch v-model="isSolo" />
                    </div>

                    <div class="rounded-lg border bg-muted/50 p-3 text-xs text-muted-foreground">
                        <template v-if="isBonanza">
                            You'll pick one model, jump straight to play, and track only your own HP, loot, and VP — the dealer + table handle the
                            rest of the FFA. No Strategy / Scheme pool / Deployment.
                        </template>
                        <template v-else-if="isSolo">
                            A scenario will be generated and you'll control both sides of the encounter. No opponent needed.
                        </template>
                        <template v-else>
                            A strategy, deployment, and scheme pool will be randomly generated when you create the game. You can share the join link
                            with your opponent.
                        </template>
                    </div>

                    <Button class="w-full" :disabled="submitting" @click="submit">
                        <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                        <Swords v-else class="mr-2 size-4" />
                        {{ submitting ? 'Creating…' : 'Create Game' }}
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
