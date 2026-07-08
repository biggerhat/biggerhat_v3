<script setup lang="ts">
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/composables/useToast';
import { csrfToken } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Check, Heart, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface MiniatureOption {
    id: number;
    display_name: string;
}

type WishlistableType = 'character' | 'miniature' | 'package' | 'unit' | 'unit_sculpt';

const BUCKET_KEYS: Record<WishlistableType, 'characters' | 'miniatures' | 'packages' | 'units' | 'unit_sculpts'> = {
    character: 'characters',
    miniature: 'miniatures',
    package: 'packages',
    unit: 'units',
    unit_sculpt: 'unit_sculpts',
};

// The "entire X vs. one specific sculpt" sub-choice — Character offers
// Miniature picks, Unit offers UnitSculpt picks.
const SUB_TYPE: Partial<Record<WishlistableType, WishlistableType>> = {
    character: 'miniature',
    unit: 'unit_sculpt',
};

const props = withDefaults(
    defineProps<{
        type: WishlistableType;
        id: number;
        miniatures?: MiniatureOption[];
        currentMiniatureId?: number;
    }>(),
    {
        miniatures: () => [],
        currentMiniatureId: undefined,
    },
);

const page = usePage<SharedData>();
const toast = useToast();
const wishlists = computed(() => page.props.auth.wishlists ?? []);
const wishlistItems = computed(() => page.props.auth.wishlist_items ?? {});
const isAuthenticated = computed(() => !!page.props.auth.user);

const selectedWishlist = ref<string | null>(null);
const selectedTarget = ref<string>('character');
const processing = ref(false);
const added = ref(false);

const subType = computed(() => SUB_TYPE[props.type]);
const hasMiniatureChoice = computed(() => !!subType.value && props.miniatures.length > 0);

const alreadyOnWishlist = computed(() => {
    if (!selectedWishlist.value) return false;
    const wid = Number(selectedWishlist.value);
    const bucket = wishlistItems.value[wid];
    if (!bucket) return false;

    if (hasMiniatureChoice.value && selectedTarget.value.startsWith('sub:')) {
        const subId = Number(selectedTarget.value.split(':')[1]);
        return bucket[BUCKET_KEYS[subType.value!]].includes(subId);
    }

    return bucket[BUCKET_KEYS[props.type]].includes(props.id);
});

const wishlistedOn = computed(() => {
    const key = BUCKET_KEYS[props.type];
    const result: string[] = [];
    for (const wl of wishlists.value) {
        const bucket = wishlistItems.value[wl.id];
        if (!bucket) continue;
        if (bucket[key].includes(props.id)) {
            result.push(wl.name);
        }
    }
    return result;
});

const targetOptions = computed(() => {
    if (!hasMiniatureChoice.value) return [];
    const opts: Array<{ value: string; label: string }> = [{ value: props.type, label: props.type === 'unit' ? 'Entire Unit' : 'Entire Character' }];
    for (const m of props.miniatures) {
        opts.push({ value: `sub:${m.id}`, label: m.display_name });
    }
    return opts;
});

async function addToWishlist() {
    if (!selectedWishlist.value) return;

    let addType: WishlistableType = props.type;
    let addId = props.id;

    if (hasMiniatureChoice.value && selectedTarget.value.startsWith('sub:')) {
        addType = subType.value!;
        addId = Number(selectedTarget.value.split(':')[1]);
    }

    processing.value = true;

    // Optimistically update shared wishlist data
    const wid = Number(selectedWishlist.value);
    const items = page.props.auth.wishlist_items;
    if (!items[wid]) {
        items[wid] = { characters: [], miniatures: [], packages: [], units: [], unit_sculpts: [] };
    }
    const key = BUCKET_KEYS[addType];
    if (!items[wid][key].includes(addId)) {
        items[wid][key].push(addId);
    }

    try {
        const res = await fetch(route('wishlists.items.add', selectedWishlist.value), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ type: addType, id: addId }),
        });
        if (!res.ok) throw new Error(`status ${res.status}`);
        added.value = true;
        setTimeout(() => (added.value = false), 2000);
        const wishlistName = wishlists.value.find((w) => w.id === wid)?.name ?? 'wishlist';
        toast.success(`Added to ${wishlistName}`);
    } catch {
        // Roll back the optimistic update.
        const bucket = items[wid];
        if (bucket) {
            const idx = bucket[key].indexOf(addId);
            if (idx !== -1) bucket[key].splice(idx, 1);
        }
        toast.error('Could not add to wishlist', { description: 'Try again in a moment.' });
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Card v-if="isAuthenticated">
        <CardHeader class="pb-3">
            <HeadingEyebrow>Wishlist</HeadingEyebrow>
        </CardHeader>
        <CardContent>
            <div v-if="wishlistedOn.length > 0" class="mb-3 flex flex-col gap-1">
                <div v-for="name in wishlistedOn" :key="name" class="flex items-center gap-1.5 text-sm text-green-600 dark:text-green-400">
                    <Check class="size-3.5 shrink-0" />
                    <span class="truncate">{{ name }}</span>
                </div>
            </div>
            <div v-if="wishlists.length === 0" class="text-center text-sm text-muted-foreground">
                <p>No wishlists yet.</p>
                <a :href="route('wishlists.index')" class="mt-1 inline-flex items-center gap-1 text-primary hover:underline">
                    <Plus class="size-3" />
                    Create one
                </a>
            </div>
            <div v-else class="flex flex-col gap-2">
                <Select v-model="selectedWishlist">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Select wishlist..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="wl in wishlists" :key="wl.id" :value="String(wl.id)">
                            {{ wl.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select v-if="hasMiniatureChoice" v-model="selectedTarget">
                    <SelectTrigger class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="opt in targetOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button
                    :variant="added || alreadyOnWishlist ? 'default' : 'outline'"
                    class="w-full gap-2"
                    :class="added || alreadyOnWishlist ? 'bg-green-600 hover:bg-green-700' : ''"
                    :disabled="!selectedWishlist || processing || alreadyOnWishlist"
                    @click="addToWishlist"
                >
                    <Check v-if="added || alreadyOnWishlist" class="size-4" />
                    <Heart v-else class="size-4" />
                    {{ added ? 'Added!' : alreadyOnWishlist ? 'On Wishlist' : 'Add to Wishlist' }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
