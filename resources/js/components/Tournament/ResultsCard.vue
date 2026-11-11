<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useToast } from '@/composables/useToast';
import { resultsImageBlob, type ResultsImageData } from '@/lib/tournamentResultsImage';
import type { StandingEntry } from '@/types/tournament';
import { FileText, Image as ImageIcon, Share2, Trophy } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/**
 * The finished event's headline: a podium plus the ways to take it with you — a PDF results sheet, a shareable image
 * (drawn in the browser) and the native share sheet where the device has one.
 */
const props = defineProps<{
    name: string;
    subtitle: string;
    standings: StandingEntry[];
    factions: Record<string, { name: string }>;
    pdfUrl: string;
    pageUrl: string;
}>();

const toast = useToast();

const places = computed(() =>
    props.standings
        .filter((s) => !s.is_ringer && s.rank !== null && s.rank <= 3)
        .slice(0, 3)
        .map((s) => ({
            rank: s.rank as number,
            name: s.display_name,
            sub: s.members?.length ? s.members.join(' · ') : (s.allegiance?.name ?? (s.faction ? (props.factions[s.faction]?.name ?? null) : null)),
            score: `${s.total_tp} TP · ${s.wins ?? 0}-${s.losses ?? 0}-${s.ties ?? 0}`,
        })),
);

const MEDAL_STYLE: Record<number, string> = {
    1: 'border-amber-500/60 bg-amber-500/10',
    2: 'border-slate-400/60 bg-slate-400/10',
    3: 'border-orange-700/60 bg-orange-700/10',
};
const ordinal = (n: number) => (n === 1 ? '1st' : n === 2 ? '2nd' : n === 3 ? '3rd' : `${n}th`);

const imageData = computed<ResultsImageData>(() => ({
    title: props.name,
    subtitle: props.subtitle,
    places: places.value,
    footer: 'BiggerHat · Tournament Tracker',
}));

const busy = ref(false);
const fileName = computed(
    () =>
        `${
            props.name
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '') || 'tournament'
        }-results.png`,
);

const makeImage = async () => {
    busy.value = true;
    try {
        return await resultsImageBlob(imageData.value);
    } catch {
        toast.error('Could not create the image in this browser.');
        return null;
    } finally {
        busy.value = false;
    }
};

const downloadImage = async () => {
    const blob = await makeImage();
    if (!blob) return;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = fileName.value;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 10_000);
};

const canShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function';
const share = async () => {
    const blob = await makeImage();
    if (!blob) return;
    const file = new File([blob], fileName.value, { type: 'image/png' });
    const base = { title: props.name, text: `${props.name} — final results`, url: props.pageUrl };
    try {
        if (navigator.canShare?.({ files: [file] })) await navigator.share({ ...base, files: [file] });
        else await navigator.share(base);
    } catch (e) {
        // Closing the share sheet rejects with AbortError; that isn't a failure.
        if ((e as DOMException).name !== 'AbortError') toast.error('Sharing did not work on this device.');
    }
};
</script>

<template>
    <Card v-if="places.length" class="mb-6 border-amber-500/30 bg-amber-500/5" data-no-print>
        <CardContent class="space-y-4 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="flex items-center gap-2 text-lg font-bold"><Trophy class="size-5 text-amber-600 dark:text-amber-400" /> Final results</h2>
                <div class="flex flex-wrap gap-2">
                    <Button as-child variant="outline" size="sm" class="h-8 gap-1.5">
                        <a :href="pdfUrl" target="_blank" rel="noopener"><FileText class="size-3.5" /> PDF</a>
                    </Button>
                    <Button variant="outline" size="sm" class="h-8 gap-1.5" :disabled="busy" @click="downloadImage">
                        <ImageIcon class="size-3.5" /> Image
                    </Button>
                    <Button v-if="canShare" size="sm" class="h-8 gap-1.5" :disabled="busy" @click="share"><Share2 class="size-3.5" /> Share</Button>
                </div>
            </div>
            <ol class="grid gap-2 sm:grid-cols-3" aria-label="Podium">
                <li v-for="p in places" :key="p.rank + p.name" class="rounded-lg border p-3 text-center" :class="MEDAL_STYLE[Math.min(p.rank, 3)]">
                    <div class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ ordinal(p.rank) }}</div>
                    <div class="truncate text-lg font-bold">{{ p.name }}</div>
                    <div v-if="p.sub" class="truncate text-xs text-muted-foreground">{{ p.sub }}</div>
                    <div class="mt-1 text-sm font-medium tabular-nums">{{ p.score }}</div>
                </li>
            </ol>
        </CardContent>
    </Card>
</template>
