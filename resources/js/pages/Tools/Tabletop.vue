<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import TabletopBoard from '@/components/Tabletop/TabletopBoard.vue';
import type { Token } from '@/lib/tabletop/geometry';
import { sampleTokens } from '@/lib/tabletop/sampleTable';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';

// Sandbox: the demo table with free-standing models. Nothing leaves this device.
const STORAGE_KEY = 'tabletop-spike-v1';
const tokens = ref<Token[]>(sampleTokens.map((t) => ({ ...t })));

onMounted(() => {
    try {
        const saved = JSON.parse(localStorage.getItem(STORAGE_KEY) ?? 'null');
        if (Array.isArray(saved?.tokens)) tokens.value = saved.tokens;
    } catch {
        // storage unavailable: the table simply starts from the demo layout
    }
});
watch(
    tokens,
    () => {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({ tokens: tokens.value }));
        } catch {
            // ignore
        }
    },
    { deep: true },
);

const moved = (t: Token) => (tokens.value = tokens.value.map((x) => (x.id === t.id ? t : x)));
const add = (t: Omit<Token, 'id'>) => tokens.value.push({ ...t, id: `n${Date.now().toString(36)}` });
const remove = (id: string) => (tokens.value = tokens.value.filter((t) => t.id !== id));
const reset = () => (tokens.value = sampleTokens.map((t) => ({ ...t })));
</script>

<template>
    <Head title="Tabletop (preview)" />
    <PageBanner title="Tabletop">
        <template #subtitle>
            <div class="px-2 text-sm text-muted-foreground">
                Preview: a 36" x 36" board with to-scale bases, terrain on several levels and a ruler. Positions stay on this device.
            </div>
        </template>
    </PageBanner>
    <div class="container mx-auto mt-4 px-2 sm:px-4 lg:px-6">
        <TabletopBoard :tokens="tokens" sandbox @moved="moved" @add="add" @remove="remove" @reset="reset" />
    </div>
</template>
