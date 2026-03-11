<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { imageLabel, imageSrc } from '@/composables/useBlueprintImages';
import { Link } from '@inertiajs/vue3';
import { FileImage, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface BlueprintCharacter {
    display_name: string;
    slug: string;
    standard_miniatures?: { id: number; slug: string }[];
}

interface BlueprintData {
    id: number;
    name: string;
    slug: string;
    source_url: string | null;
    image_path: string | null;
    sculpt_version: string;
    published_at: string | null;
    characters?: BlueprintCharacter[];
    characters_count?: number;
}

const props = defineProps<{
    blueprint: BlueprintData;
}>();

const characterCount = computed(() => props.blueprint.characters_count ?? props.blueprint.characters?.length ?? 0);
const singleCharacter = computed(() =>
    characterCount.value === 1 && props.blueprint.characters?.length === 1 ? props.blueprint.characters[0] : null,
);

const formatVersion = (version: string) => {
    return version ? version.replace(/_/g, ' ') : '';
};
</script>

<template>
    <Card class="flex flex-col overflow-hidden">
        <!-- Thumbnail — opens full-size dialog -->
        <Dialog v-if="blueprint.image_path">
            <DialogTrigger as-child>
                <button class="cursor-pointer border-b bg-muted/30 transition-opacity hover:opacity-80">
                    <img
                        :src="imageSrc(blueprint.image_path)"
                        :alt="blueprint.name"
                        loading="lazy"
                        decoding="async"
                        class="h-40 w-full object-contain"
                    />
                </button>
            </DialogTrigger>
            <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogTitle class="text-lg font-semibold">{{ blueprint.name }}</DialogTitle>
                <DialogDescription class="text-sm text-muted-foreground">
                    {{ imageLabel(blueprint.image_path) }}
                </DialogDescription>
                <img :src="imageSrc(blueprint.image_path)" :alt="blueprint.name" class="mt-3 w-full rounded-lg border" />
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

            <div v-else-if="characterCount > 1" class="flex flex-wrap gap-1">
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
                    class="text-primary hover:underline"
                >
                    {{ character.display_name }}
                </Link>
            </div>

            <span v-else class="text-muted-foreground">No linked characters</span>
        </div>
    </Card>
</template>
