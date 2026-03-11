<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { imageLabel, imageSrc } from '@/composables/useBlueprintImages';
import { Link } from '@inertiajs/vue3';
import { FileImage, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface BlueprintCharacter {
    display_name: string;
    slug: string;
    standard_miniatures?: { id: number; slug: string }[];
}

interface BlueprintData {
    id: number;
    name: string;
    slug: string;
    image: string | null;
    images: string[] | null;
    source_url: string | null;
    sculpt_version: string;
    published_at: string | null;
    characters?: BlueprintCharacter[];
    characters_count?: number;
    miniatures_count?: number;
    packages_count?: number;
}

const props = defineProps<{
    blueprint: BlueprintData;
}>();

const characterCount = computed(() => props.blueprint.characters_count ?? props.blueprint.characters?.length ?? 0);
const singleCharacter = computed(() => (characterCount.value === 1 && props.blueprint.characters?.length === 1 ? props.blueprint.characters[0] : null));
const allImages = computed(() => props.blueprint.images ?? (props.blueprint.image ? [props.blueprint.image] : []));
const imageCount = computed(() => allImages.value.length);
const thumbnailUrl = computed(() => allImages.value[0] ?? null);

const formatVersion = (version: string) => {
    return version ? version.replace(/_/g, ' ') : '';
};

const selectedImage = ref<string | null>(null);
</script>

<template>
    <Card class="flex flex-col overflow-hidden">
        <!-- Thumbnail — opens image gallery dialog -->
        <Dialog v-if="allImages.length > 0">
            <DialogTrigger as-child>
                <button v-if="thumbnailUrl" class="cursor-pointer border-b bg-muted/30 transition-opacity hover:opacity-80">
                    <img :src="imageSrc(thumbnailUrl)" :alt="blueprint.name" loading="lazy" decoding="async" class="h-40 w-full object-contain" />
                </button>
            </DialogTrigger>
            <DialogContent class="max-h-[90vh] max-w-5xl overflow-y-auto">
                <DialogTitle class="text-lg font-semibold">{{ blueprint.name }}</DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground">
                    {{ imageCount }} assembly diagram{{ imageCount !== 1 ? 's' : '' }}
                </DialogDescription>

                <!-- Selected image expanded view -->
                <div v-if="selectedImage" class="mt-3">
                    <button class="mb-2 text-xs text-primary hover:underline" @click="selectedImage = null">&larr; Back to all</button>
                    <img :src="imageSrc(selectedImage)" :alt="imageLabel(selectedImage)" loading="lazy" decoding="async" class="w-full rounded-lg border" />
                    <p class="mt-2 text-center text-sm font-medium text-muted-foreground">{{ imageLabel(selectedImage) }}</p>
                </div>

                <!-- Image grid -->
                <div v-else class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    <button
                        v-for="(img, idx) in allImages"
                        :key="idx"
                        class="group cursor-pointer overflow-hidden rounded-lg border transition-all hover:shadow-md"
                        @click="selectedImage = img"
                    >
                        <img :src="imageSrc(img)" :alt="imageLabel(img)" loading="lazy" decoding="async" class="w-full" />
                        <div class="border-t bg-muted/50 px-2 py-1.5">
                            <p class="truncate text-[11px] font-medium text-muted-foreground group-hover:text-foreground">
                                {{ imageLabel(img) }}
                            </p>
                        </div>
                    </button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Header -->
        <div class="flex items-center gap-2 border-b bg-secondary px-3 py-2">
            <FileImage class="h-4 w-4 shrink-0 text-muted-foreground" />
            <h3 class="min-w-0 flex-1 truncate text-sm font-semibold leading-tight">{{ blueprint.name }}</h3>
        </div>

        <!-- Body -->
        <div class="flex-1 px-3 py-2.5">
            <div class="flex flex-wrap items-center gap-1.5">
                <Badge variant="outline" class="text-[10px] capitalize">{{ formatVersion(blueprint.sculpt_version) }}</Badge>
                <Badge v-if="imageCount > 0" variant="secondary" class="text-[10px]">{{ imageCount }} diagram{{ imageCount !== 1 ? 's' : '' }}</Badge>
            </div>
        </div>

        <!-- Footer: characters -->
        <div class="flex items-center gap-1.5 border-t px-3 py-1.5 text-xs">
            <Users class="h-3 w-3 shrink-0 text-muted-foreground" />

            <Link
                v-if="singleCharacter"
                :href="
                    route('characters.view', {
                        character: singleCharacter.slug,
                        miniature: singleCharacter.standard_miniatures?.[0]?.id,
                        slug: singleCharacter.standard_miniatures?.[0]?.slug ?? 'view',
                    })
                "
                class="text-primary hover:underline"
            >
                {{ singleCharacter.display_name }}
            </Link>

            <Sheet v-else-if="characterCount > 1">
                <SheetTrigger as-child>
                    <button class="cursor-pointer text-primary hover:underline">{{ characterCount }} Characters</button>
                </SheetTrigger>
                <SheetContent side="right" class="overflow-y-auto">
                    <SheetTitle class="text-lg font-semibold">{{ blueprint.name }}</SheetTitle>
                    <SheetDescription class="text-sm text-muted-foreground">
                        Build instructions for {{ characterCount }} characters
                    </SheetDescription>
                    <div class="mt-4 space-y-1">
                        <Link
                            v-for="character in blueprint.characters"
                            :key="character.slug"
                            :href="
                                route('characters.view', {
                                    character: character.slug,
                                    miniature: character.standard_miniatures?.[0]?.id,
                                    slug: character.standard_miniatures?.[0]?.slug ?? 'view',
                                })
                            "
                            class="flex items-center gap-2 rounded-md px-2 py-2 transition-colors hover:bg-accent"
                        >
                            <Users class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <span class="text-sm font-medium">{{ character.display_name }}</span>
                        </Link>
                    </div>
                </SheetContent>
            </Sheet>

            <span v-else class="text-muted-foreground">No linked characters</span>
        </div>
    </Card>
</template>
