<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { csrfToken } from '@/lib/utils';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Check, Heart, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface MiniatureOption {
    id: number;
    display_name: string;
}

const props = withDefaults(
    defineProps<{
        type: 'character' | 'miniature' | 'package';
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
const wishlists = computed(() => page.props.auth.wishlists ?? []);
const wishlistItems = computed(() => page.props.auth.wishlist_items ?? {});
const isAuthenticated = computed(() => !!page.props.auth.user);

const selectedWishlist = ref<string | null>(null);
const selectedTarget = ref<string>('character');
const processing = ref(false);
const added = ref(false);

const hasMiniatureChoice = computed(() => props.type === 'character' && props.miniatures.length > 0);

const alreadyOnWishlist = computed(() => {
    if (!selectedWishlist.value) return false;
    const wid = Number(selectedWishlist.value);
    const bucket = wishlistItems.value[wid];
    if (!bucket) return false;

    if (hasMiniatureChoice.value && selectedTarget.value.startsWith('miniature:')) {
        const miniId = Number(selectedTarget.value.split(':')[1]);
        return bucket.miniatures.includes(miniId);
    }

    const key = props.type === 'character' ? 'characters' : props.type === 'package' ? 'packages' : 'miniatures';
    return bucket[key].includes(props.id);
});

const wishlistedOn = computed(() => {
    const key = props.type === 'character' ? 'characters' : props.type === 'package' ? 'packages' : 'miniatures';
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
    const opts: Array<{ value: string; label: string }> = [{ value: 'character', label: 'Entire Character' }];
    for (const m of props.miniatures) {
        opts.push({ value: `miniature:${m.id}`, label: m.display_name });
    }
    return opts;
});

async function addToWishlist() {
    if (!selectedWishlist.value) return;

    let addType = props.type;
    let addId = props.id;

    if (hasMiniatureChoice.value && selectedTarget.value.startsWith('miniature:')) {
        addType = 'miniature';
        addId = Number(selectedTarget.value.split(':')[1]);
    }

    processing.value = true;

    // Optimistically update shared wishlist data
    const wid = Number(selectedWishlist.value);
    const items = page.props.auth.wishlist_items;
    if (!items[wid]) {
        items[wid] = { characters: [], miniatures: [], packages: [] };
    }
    const key = addType === 'character' ? 'characters' : addType === 'package' ? 'packages' : 'miniatures';
    if (!items[wid][key].includes(addId)) {
        items[wid][key].push(addId);
    }

    try {
        await fetch(route('wishlists.items.add', selectedWishlist.value), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ type: addType, id: addId }),
        });
        added.value = true;
        setTimeout(() => (added.value = false), 2000);
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Card v-if="isAuthenticated">
        <CardHeader class="pb-3">
            <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Wishlist</CardTitle>
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
