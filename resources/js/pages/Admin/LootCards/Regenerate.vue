<script setup lang="ts">
import BonanzaSplitCard from '@/components/Bonanza/BonanzaSplitCard.vue';
import { Button } from '@/components/ui/button';
import { captureLootCardImage } from '@/composables/useLootCardCapture';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';

interface EntityRef {
    id: number;
    name: string;
    [key: string]: unknown;
}
interface Card {
    id: number;
    slug: string;
    name: string;
    suit: string;
    value_label: string;
    title_a: string | null;
    title_b: string | null;
    effect_a: string | null;
    effect_b: string | null;
    side_a_actions: EntityRef[];
    side_b_actions: EntityRef[];
    side_a_abilities: EntityRef[];
    side_b_abilities: EntityRef[];
    side_a_triggers: EntityRef[];
    side_b_triggers: EntityRef[];
}

const props = defineProps<{ cards: Card[] }>();

const total = props.cards.length;
const running = ref(false);
const done = ref(0);
const current = ref('');
const errors = ref<string[]>([]);
const finished = ref(false);

const regenerate = async () => {
    if (running.value) return;
    running.value = true;
    finished.value = false;
    done.value = 0;
    errors.value = [];

    // Force light mode once for the whole batch (printer-friendly capture);
    // captureLootCardImage then no-ops its own toggle. Restored at the end.
    const root = document.documentElement;
    const wasDark = root.classList.contains('dark');
    if (wasDark) root.classList.remove('dark');

    try {
        for (const card of props.cards) {
            current.value = card.name;
            const host = document.querySelector(`[data-cap="${card.id}"]`);
            const target = host?.firstElementChild as HTMLElement | null;
            if (!target) {
                errors.value.push(`${card.name}: nothing to capture`);
                done.value++;
                continue;
            }
            const file = await captureLootCardImage(target, card.name);
            if (!file) {
                errors.value.push(`${card.name}: capture failed`);
                done.value++;
                continue;
            }
            try {
                const fd = new FormData();
                fd.append('image', file);
                await axios.post(route('admin.loot_cards.image', card.slug), fd);
            } catch {
                errors.value.push(`${card.name}: upload failed`);
            }
            done.value++;
        }
    } finally {
        if (wasDark) root.classList.add('dark');
        current.value = '';
        running.value = false;
        finished.value = true;
    }
};
</script>

<template>
    <Head title="Regenerate Loot Card Images" />
    <div class="container mx-auto max-w-2xl space-y-4 p-6">
        <Link :href="route('admin.loot_cards.index')" class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-3" /> Loot Cards
        </Link>

        <div>
            <h1 class="text-xl font-bold">Regenerate Print Images</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Re-captures every card image in printer-friendly light mode (white background, dark text, coloured suit borders). Run this once after
                a styling change so the existing {{ total }} cards match. Keep this tab focused while it runs.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <Button :disabled="running" @click="regenerate">{{ running ? 'Regenerating…' : `Regenerate all ${total} images` }}</Button>
            <span v-if="running || finished" class="text-sm tabular-nums">
                {{ done }} / {{ total }}<span v-if="current" class="text-muted-foreground"> — {{ current }}</span>
            </span>
        </div>

        <p v-if="finished && !errors.length" class="text-sm font-medium text-emerald-600">Done — all images regenerated.</p>
        <ul v-if="errors.length" class="list-disc space-y-0.5 pl-5 text-xs text-rose-600">
            <li v-for="(e, i) in errors" :key="i">{{ e }}</li>
        </ul>

        <!-- Offscreen render targets: one BonanzaSplitCard per card, matching the
             form's capture config (mirror, no toggle, no inline image). -->
        <div aria-hidden="true" class="pointer-events-none fixed -left-[9999px] top-0 select-none">
            <div v-for="card in cards" :key="card.id" :data-cap="card.id">
                <BonanzaSplitCard
                    :name="card.name"
                    :suit="card.suit"
                    :value-label="card.value_label"
                    :image="null"
                    :side-a="{
                        title: card.title_a,
                        effect: card.effect_a,
                        abilities: card.side_a_abilities,
                        actions: card.side_a_actions,
                        triggers: card.side_a_triggers,
                    }"
                    :side-b="{
                        title: card.title_b,
                        effect: card.effect_b,
                        abilities: card.side_b_abilities,
                        actions: card.side_b_actions,
                        triggers: card.side_b_triggers,
                    }"
                    :mirror="true"
                    :hide-toggle="true"
                />
            </div>
        </div>
    </div>
</template>
