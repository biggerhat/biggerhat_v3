<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { csrfToken } from '@/lib/utils';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { BookMarked, Heart, Maximize2, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const flipped = ref(false);
const fullscreenOpen = ref(false);

const flip = () => {
    flipped.value = !flipped.value;
};

const props = defineProps({
    miniature: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    showLink: {
        type: [Boolean],
        required: false,
        default() {
            return true;
        },
    },
    showCollection: {
        type: [Boolean],
        required: false,
        default() {
            return true;
        },
    },
    characterSlug: {
        type: String,
        required: false,
        default() {
            return '';
        },
    },
    allMiniatureIds: {
        type: Array as () => number[],
        required: false,
        default() {
            return [];
        },
    },
});

const page = usePage<SharedData>();

const miniatureIds = computed(() => (props.allMiniatureIds.length ? props.allMiniatureIds : [props.miniature?.id].filter(Boolean)));

const inCollection = computed(() => {
    const collectionIds = page.props.auth?.collection_miniature_ids ?? [];
    return miniatureIds.value.some((id) => collectionIds.includes(id));
});

const onWishlist = computed(() => {
    const wishlistItems = page.props.auth?.wishlist_items ?? {};
    const characterId = props.miniature?.character_id;
    return Object.values(wishlistItems).some(
        (wl) => miniatureIds.value.some((id) => wl.miniatures.includes(id)) || (characterId && wl.characters.includes(characterId)),
    );
});

const isLoggedIn = computed(() => !!page.props.auth?.user);

const addingToCollection = ref(false);
const addToCollection = async () => {
    const characterId = props.miniature?.character_id;
    if (!characterId || inCollection.value) return;
    addingToCollection.value = true;

    // Optimistically update shared auth data
    const ids = page.props.auth.collection_miniature_ids;
    for (const id of miniatureIds.value) {
        if (!ids.includes(id)) ids.push(id);
    }

    try {
        await fetch(route('collection.add_character'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ character_id: characterId }),
        });
    } finally {
        addingToCollection.value = false;
    }
};

const currentImage = computed(() => {
    const path = flipped.value ? props.miniature.back_image : props.miniature.front_image;
    return '/storage/' + path;
});

const currentLabel = computed(() => {
    return props.miniature.display_name + (flipped.value ? ' (back)' : ' (front)');
});
</script>

<template>
    <div class="w-full rounded-lg text-center transition-shadow duration-300 hover:shadow-lg hover:shadow-black/20">
        <p class="mb-1 text-xs text-muted-foreground">{{ miniature.display_name }}</p>
        <div class="relative mx-auto w-fit">
            <div @click="flip" @keydown.enter="flip" role="button" tabindex="0" class="cursor-pointer" style="perspective: 1000px">
                <div
                    class="card-flip-inner relative w-full"
                    :class="{ 'card-flipped': flipped }"
                    style="transition: transform 0.5s; transform-style: preserve-3d"
                >
                    <div class="card-face" style="backface-visibility: hidden">
                        <img
                            :src="'/storage/' + miniature.front_image"
                            :alt="miniature.display_name"
                            loading="lazy"
                            decoding="async"
                            class="aspect-[550/950] h-full w-full rounded-lg object-cover"
                        />
                    </div>
                    <div class="card-face absolute inset-0" style="backface-visibility: hidden; transform: rotateY(180deg)">
                        <img
                            :src="'/storage/' + miniature.back_image"
                            :alt="miniature.display_name"
                            loading="lazy"
                            decoding="async"
                            class="aspect-[550/950] h-full w-full rounded-lg object-cover"
                        />
                    </div>
                </div>
            </div>
            <button
                @click.stop="fullscreenOpen = true"
                class="absolute bottom-3 right-2 rounded-full bg-black/40 p-1.5 text-white/70 backdrop-blur-sm transition-all hover:bg-black/70 hover:text-white"
                title="View fullscreen"
            >
                <Maximize2 class="size-3.5" />
            </button>
        </div>
        <div v-if="props.showCollection && (inCollection || onWishlist)" class="mt-1 flex items-center justify-center gap-2">
            <span v-if="inCollection" class="flex items-center gap-1 text-[11px]" style="color: #059669">
                <BookMarked class="size-3" />
                Collected
            </span>
            <span v-if="onWishlist" class="flex items-center gap-1 text-[11px]" style="color: #f43f5e">
                <Heart class="size-3 fill-current" />
                Wishlisted
            </span>
        </div>
        <div v-if="props.showCollection && isLoggedIn && !inCollection" class="mt-1">
            <Button
                variant="outline"
                size="sm"
                class="h-6 gap-1 text-[11px] text-muted-foreground hover:text-foreground"
                :disabled="addingToCollection"
                @click="addToCollection"
            >
                <Plus class="size-3" />
                Add to Collection
            </Button>
        </div>
        <div class="mt-1" v-if="props.showLink === true">
            <Button
                @click="
                    router.get(
                        route('characters.view', { character: props.characterSlug, miniature: props.miniature.id, slug: props.miniature.slug }),
                    )
                "
                size="sm"
                variant="link"
            >
                View Character Page
            </Button>
        </div>

        <Dialog v-model:open="fullscreenOpen">
            <DialogContent class="fullscreen-card-dialog max-h-[95dvh] max-w-[95vw] border-none bg-black/95 p-2 sm:max-w-fit sm:p-4">
                <DialogTitle class="sr-only">{{ currentLabel }}</DialogTitle>
                <div class="flex items-center justify-center">
                    <img
                        :src="currentImage"
                        :alt="currentLabel"
                        loading="lazy"
                        decoding="async"
                        class="max-h-[90dvh] w-auto rounded-lg object-contain"
                    />
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.card-flipped {
    transform: rotateY(180deg);
}
</style>

<style>
.fullscreen-card-dialog > button {
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
    color: white;
    opacity: 1;
    border-radius: 9999px;
    padding: 0.375rem;
}

.fullscreen-card-dialog > button:hover {
    background-color: rgba(0, 0, 0, 0.85);
}
</style>
