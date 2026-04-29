<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { CircleX, Crown, Search, Swords } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface SpecialRule {
    id: number;
    slug: string;
    name: string;
}

interface Sculpt {
    id: number;
    slug: string;
    name: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
}

interface UnitMin {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    restriction: string | null;
    special_unit_rules: SpecialRule[];
    sculpts?: Sculpt[];
    hire_category?: 'direct' | 'neutral';
}

const props = defineProps<{
    pool: UnitMin[];
    allegianceSlug: string;
    allegianceName: string;
    allegianceColorSlug: string | null;
}>();

const emit = defineEmits<{
    (e: 'preview', unit: UnitMin): void;
    (e: 'hire', unit: UnitMin): void;
}>();

const filterText = ref('');

const commanders = computed(() => {
    const text = filterText.value.trim().toLowerCase();
    return props.pool
        .filter((u) => u.special_unit_rules.some((r) => r.slug === 'commander'))
        .filter((u) => !text || u.name.toLowerCase().includes(text) || (u.title?.toLowerCase().includes(text) ?? false))
        .sort((a, b) => b.scrip - a.scrip || a.name.localeCompare(b.name));
});

const accentBg = computed(() =>
    props.allegianceColorSlug ? `bg-${props.allegianceColorSlug}` : 'bg-primary/40',
);
</script>

<template>
    <Card class="overflow-hidden">
        <div :class="['h-1 w-full', accentBg]" />
        <CardContent class="p-4 sm:p-6">
            <!-- Hero header -->
            <div class="flex flex-col items-center text-center">
                <div class="relative mb-3 flex size-16 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500/20 to-amber-500/5 ring-1 ring-amber-500/30 sm:size-20">
                    <Crown class="size-8 text-amber-500 sm:size-10" />
                    <div class="absolute -bottom-1 -right-1 flex size-7 items-center justify-center rounded-full bg-background ring-2 ring-amber-500/40">
                        <AllegianceLogo :allegiance="allegianceSlug" class-name="size-5" />
                    </div>
                </div>
                <h2 class="text-balance text-lg font-bold sm:text-xl">Choose your Commander</h2>
                <p class="mt-1 max-w-md text-balance text-xs text-muted-foreground sm:text-sm">
                    The Commander sets the Scrip budget for your <strong>{{ allegianceName }}</strong> Company. Their cost
                    becomes the pool every other hire spends from.
                </p>
            </div>

            <!-- Search -->
            <div class="relative mx-auto mt-4 max-w-md">
                <Search class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="filterText" placeholder="Search Commanders…" class="pl-9 pr-8" />
                <button
                    v-if="filterText"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    aria-label="Clear search"
                    @click="filterText = ''"
                >
                    <CircleX class="size-4" />
                </button>
            </div>

            <!-- Commander grid -->
            <div v-if="commanders.length" class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="c in commanders"
                    :key="c.id"
                    type="button"
                    class="group relative flex flex-col overflow-hidden rounded-xl border-2 bg-card text-left transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-500/50 hover:shadow-lg"
                    @click="emit('preview', c)"
                >
                    <div :class="['h-1 w-full', accentBg]" />
                    <CardImage
                        :src="c.sculpts?.[0]?.combination_image ?? c.sculpts?.[0]?.front_image ?? null"
                        :alt="c.name"
                        :allegiance-slug="allegianceSlug"
                        :placeholder-icon="Swords"
                        rounded-class=""
                        aspect-class="aspect-[5/4]"
                    />
                    <div class="flex flex-1 flex-col gap-1.5 p-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="truncate text-sm font-semibold">{{ c.name }}</span>
                            <Badge class="shrink-0 bg-emerald-500/15 px-1.5 py-0 text-[10px] tabular-nums text-emerald-700 dark:text-emerald-400">
                                +{{ c.scrip }}s
                            </Badge>
                        </div>
                        <p v-if="c.title" class="truncate text-[11px] italic text-muted-foreground">{{ c.title }}</p>
                        <div class="mt-auto flex flex-wrap gap-1">
                            <Badge
                                v-if="c.hire_category === 'neutral'"
                                variant="outline"
                                class="px-1 py-0 text-[9px]"
                            >Neutral</Badge>
                            <Badge
                                v-for="r in c.special_unit_rules.filter((r) => r.slug !== 'commander')"
                                :key="r.id"
                                variant="outline"
                                class="px-1 py-0 text-[9px]"
                            >{{ r.name }}</Badge>
                        </div>
                        <div class="flex items-center gap-2 pt-1">
                            <Button
                                size="sm"
                                class="flex-1 gap-1 bg-amber-500 text-white hover:bg-amber-600"
                                @click.stop="emit('hire', c)"
                            >
                                <Crown class="size-3.5" /> Set as Commander
                            </Button>
                        </div>
                    </div>
                </button>
            </div>
            <div v-else class="mt-5 rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                No Commanders match your search. Try clearing the filter.
            </div>
        </CardContent>
    </Card>
</template>
