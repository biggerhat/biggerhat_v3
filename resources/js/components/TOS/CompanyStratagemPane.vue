<script setup lang="ts">
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Plus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StratagemMin {
    id: number;
    slug: string;
    name: string;
    tactical_cost: number;
    deck_source?: 'primary' | 'envoy';
}

const props = defineProps<{
    deck: StratagemMin[];
    available: StratagemMin[];
    deckSize: number;
    maxEnvoy: number;
    hasEnvoy: boolean;
}>();

const emit = defineEmits<{
    (e: 'add', s: StratagemMin): void;
    (e: 'remove', s: StratagemMin): void;
}>();

const filter = ref('');
const deckIds = computed(() => new Set(props.deck.map((s) => s.id)));
const envoyIds = computed(() => new Set(props.available.filter((s) => s.deck_source === 'envoy').map((s) => s.id)));
const deckEnvoyCount = computed(() => props.deck.filter((s) => envoyIds.value.has(s.id)).length);
const deckFull = computed(() => props.deck.length >= props.deckSize);
const envoyFull = computed(() => deckEnvoyCount.value >= props.maxEnvoy);

const availableList = computed(() => {
    const text = filter.value.trim().toLowerCase();
    return props.available.filter((s) => !deckIds.value.has(s.id) && (text === '' || s.name.toLowerCase().includes(text)));
});

const isDisabled = (s: StratagemMin): boolean => deckFull.value || (s.deck_source === 'envoy' && envoyFull.value);
</script>

<template>
    <div class="rounded-md border p-4">
        <div class="mb-3 flex items-center justify-between gap-2">
            <HeadingEyebrow>Stratagem Deck</HeadingEyebrow>
            <div class="flex items-center gap-2 text-[11px] text-muted-foreground">
                <span :class="deckFull ? 'font-semibold text-emerald-600' : ''">{{ deck.length }} / {{ deckSize }}</span>
                <span v-if="hasEnvoy">· {{ deckEnvoyCount }} / {{ maxEnvoy }} Envoy</span>
            </div>
        </div>

        <div v-if="deck.length" class="mb-3 flex flex-wrap gap-1.5">
            <span v-for="s in deck" :key="s.id" class="inline-flex items-center gap-1 rounded border bg-muted/40 px-2 py-1 text-[11px]">
                <span :class="envoyIds.has(s.id) ? 'text-sky-600 dark:text-sky-400' : ''">{{ s.name }}</span>
                <span class="text-muted-foreground">({{ s.tactical_cost }})</span>
                <button type="button" class="text-muted-foreground hover:text-rose-600" aria-label="Remove" @click="emit('remove', s)">
                    <X class="size-3" />
                </button>
            </span>
        </div>
        <p v-else class="mb-3 text-[11px] text-muted-foreground">No Stratagems selected yet.</p>

        <Input v-model="filter" placeholder="Search Stratagems…" class="mb-2 h-8 text-xs" />
        <div class="max-h-48 space-y-1 overflow-y-auto">
            <button
                v-for="s in availableList"
                :key="s.id"
                type="button"
                :disabled="isDisabled(s)"
                class="flex w-full items-center justify-between gap-2 rounded border px-2 py-1 text-left text-[11px] transition hover:border-primary/40 disabled:cursor-not-allowed disabled:opacity-40"
                @click="emit('add', s)"
            >
                <span class="flex items-center gap-1.5">
                    <Plus class="size-3 shrink-0" /> {{ s.name }}
                    <Badge
                        v-if="s.deck_source === 'envoy'"
                        variant="outline"
                        class="border-sky-500/40 px-1 py-0 text-[9px] text-sky-600 dark:text-sky-400"
                    >
                        Envoy
                    </Badge>
                </span>
                <span class="text-muted-foreground">{{ s.tactical_cost }}</span>
            </button>
            <p v-if="!availableList.length" class="py-2 text-center text-[11px] text-muted-foreground">No Stratagems match.</p>
        </div>
    </div>
</template>
