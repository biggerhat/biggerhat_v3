<script setup lang="ts">
import FlipCard from '@/components/TOS/FlipCard.vue';
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { CARD_HOVER } from '@/lib/cardHover';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { BookMarked, Heart, Plus, Swords } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface GridSculpt {
    id: number;
    slug: string;
    front_image: string | null;
    back_image: string | null;
}

interface GridUnit {
    id: number;
    name: string;
    title: string | null;
    scrip: number;
    restriction?: string | null;
    special_unit_rules: Array<{ id: number; slug?: string; name: string }>;
    sculpts: GridSculpt[];
    allegiances?: Array<{ slug: string }>;
}

const props = defineProps<{
    unit: GridUnit;
    /** Fallback allegiance tint when the unit's own allegiances aren't loaded (e.g. a filtered roster page already scoped to one allegiance). */
    allegianceSlug?: string | null;
}>();

const page = usePage<SharedData>();
const isAuthenticated = computed(() => !!page.props.auth.user);
const primarySculpt = computed(() => props.unit.sculpts[0] ?? null);

const inCollection = computed(() => {
    if (!primarySculpt.value) return false;
    return (page.props.auth.collection_unit_sculpt_ids ?? []).includes(primarySculpt.value.id);
});

const onWishlist = computed(() => {
    const items = page.props.auth.wishlist_items ?? {};
    const sculptId = primarySculpt.value?.id;
    return Object.values(items).some((wl) => wl.units.includes(props.unit.id) || (sculptId != null && wl.unit_sculpts.includes(sculptId)));
});

const addingToCollection = ref(false);
const addToCollection = () => {
    if (!primarySculpt.value || inCollection.value) return;

    const sculptId = primarySculpt.value.id;
    const ids = page.props.auth.collection_unit_sculpt_ids;
    const wasAbsent = !ids.includes(sculptId);
    if (wasAbsent) ids.push(sculptId);

    router.post(
        route('tos.collection.toggle'),
        { unit_sculpt_id: sculptId, quantity: 1 },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (addingToCollection.value = true),
            onError: () => {
                if (wasAbsent) {
                    const idx = ids.indexOf(sculptId);
                    if (idx !== -1) ids.splice(idx, 1);
                }
            },
            onFinish: () => (addingToCollection.value = false),
        },
    );
};
</script>

<template>
    <Card :class="['h-full overflow-hidden', CARD_HOVER]">
        <FlipCard
            :front-image="primarySculpt?.front_image"
            :back-image="primarySculpt?.back_image"
            :front-alt="`${unit.name} (standard)`"
            :back-alt="`${unit.name} (glory)`"
            :allegiance-slug="unit.allegiances?.[0]?.slug ?? allegianceSlug ?? null"
            :placeholder-icon="Swords"
            :single-side="!primarySculpt?.back_image"
        />
        <CardContent class="space-y-1.5 p-3">
            <div class="flex items-center justify-between gap-2">
                <span class="truncate text-sm font-semibold">{{ unit.name }}</span>
                <span
                    v-if="unit.special_unit_rules.some((r) => r.slug === 'commander')"
                    class="shrink-0 text-[11px] font-medium tabular-nums text-emerald-700 dark:text-emerald-400"
                    title="Provides starting Scrip budget"
                    >+{{ unit.scrip }}</span
                >
                <span v-else class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ unit.scrip }}</span>
            </div>
            <p v-if="unit.title" class="truncate text-[11px] italic text-muted-foreground">{{ unit.title }}</p>
            <div v-if="unit.restriction || unit.special_unit_rules.length" class="flex flex-wrap gap-1">
                <Badge v-if="unit.restriction" variant="outline" class="text-[10px] capitalize">Neutral</Badge>
                <Badge v-for="r in unit.special_unit_rules" :key="r.id" variant="outline" class="text-[10px]">{{ r.name }}</Badge>
            </div>

            <div v-if="inCollection || onWishlist" class="flex items-center gap-2 pt-0.5">
                <span v-if="inCollection" class="flex items-center gap-1 text-[11px]" style="color: #059669">
                    <BookMarked class="size-3" />
                    Collected
                </span>
                <span v-if="onWishlist" class="flex items-center gap-1 text-[11px]" style="color: #f43f5e">
                    <Heart class="size-3 fill-current" />
                    Wishlisted
                </span>
            </div>
            <button
                v-if="isAuthenticated && !inCollection && primarySculpt"
                class="flex items-center gap-1 text-[11px] text-muted-foreground transition-colors hover:text-foreground disabled:cursor-wait disabled:opacity-50"
                :disabled="addingToCollection"
                @click.prevent="addToCollection"
            >
                <Plus class="size-3" />
                Add to Collection
            </button>

            <div v-if="primarySculpt" class="pt-1">
                <Button size="sm" variant="link" class="h-6 px-0 text-[11px]" @click="router.get(route('tos.units.view', primarySculpt.slug))">
                    View Unit Page
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
