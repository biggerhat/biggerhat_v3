<script setup lang="ts">
import BonanzaSplitCard from '@/components/Bonanza/BonanzaSplitCard.vue';
import { Button } from '@/components/ui/button';
import { captureLootCardImage } from '@/composables/useLootCardCapture';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowLeft } from 'lucide-vue-next';
import { nextTick, ref } from 'vue';

interface CardStub {
    id: number;
    slug: string;
    name: string;
    suit: string;
    value_label: string;
}

const props = defineProps<{ cards: CardStub[] }>();

const total = props.cards.length;
const running = ref(false);
const done = ref(0);
const current = ref('');
const errors = ref<string[]>([]);
const finished = ref(false);

// Only one card's full data lives in memory at a time.
const activeCard = ref<Record<string, unknown> | null>(null);

const regenerate = async () => {
    if (running.value) return;
    running.value = true;
    finished.value = false;
    done.value = 0;
    errors.value = [];

    const root = document.documentElement;
    const wasDark = root.classList.contains('dark');
    if (wasDark) root.classList.remove('dark');

    try {
        for (const stub of props.cards) {
            current.value = stub.name;

            // 1. Fetch this card's full render data.
            let data: Record<string, unknown>;
            try {
                const resp = await axios.get(route('admin.loot_cards.data', stub.slug));
                data = resp.data;
            } catch {
                errors.value.push(`${stub.name}: fetch failed`);
                done.value++;
                continue;
            }

            // 2. Mount the card component.
            activeCard.value = data;
            await nextTick();
            await new Promise((r) => setTimeout(r, 100));

            // 3. Capture it.
            const host = document.querySelector('[data-cap]');
            const target = host?.firstElementChild as HTMLElement | null;
            if (!target) {
                errors.value.push(`${stub.name}: nothing to capture`);
                activeCard.value = null;
                done.value++;
                continue;
            }
            const file = await captureLootCardImage(target, stub.name);
            if (!file) {
                errors.value.push(`${stub.name}: capture failed`);
                activeCard.value = null;
                done.value++;
                continue;
            }

            // 4. Upload it.
            try {
                const fd = new FormData();
                fd.append('image', file);
                await axios.post(route('admin.loot_cards.image', stub.slug), fd);
            } catch {
                errors.value.push(`${stub.name}: upload failed`);
            }

            // 5. Tear down before the next card.
            activeCard.value = null;
            done.value++;
        }
    } finally {
        if (wasDark) root.classList.add('dark');
        activeCard.value = null;
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
                Re-captures every card image in printer-friendly light mode (white background, dark text, coloured suit borders) at 300 DPI. Processes one
                card at a time to stay within memory limits. Keep this tab focused while it runs.
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

        <!-- Single offscreen render target — one card at a time. -->
        <div aria-hidden="true" class="pointer-events-none fixed -left-[9999px] top-0 select-none">
            <div v-if="activeCard" data-cap>
                <BonanzaSplitCard
                    :name="(activeCard.name as string) ?? ''"
                    :suit="(activeCard.suit as string) ?? ''"
                    :value-label="(activeCard.value_label as string) ?? ''"
                    :image="null"
                    :side-a="{
                        title: (activeCard.title_a as string) ?? null,
                        effect: (activeCard.effect_a as string) ?? null,
                        abilities: (activeCard.side_a_abilities as []) ?? [],
                        actions: (activeCard.side_a_actions as []) ?? [],
                        triggers: (activeCard.side_a_triggers as []) ?? [],
                    }"
                    :side-b="{
                        title: (activeCard.title_b as string) ?? null,
                        effect: (activeCard.effect_b as string) ?? null,
                        abilities: (activeCard.side_b_abilities as []) ?? [],
                        actions: (activeCard.side_b_actions as []) ?? [],
                        triggers: (activeCard.side_b_triggers as []) ?? [],
                    }"
                    :mirror="true"
                    :hide-toggle="true"
                />
            </div>
        </div>
    </div>
</template>
