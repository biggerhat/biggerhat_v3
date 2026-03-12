<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Link } from '@inertiajs/vue3';
import { BookOpen, ExternalLink, FileText, Library, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface LoreCharacter {
    display_name: string;
    slug: string;
    standard_miniatures?: { id: number; slug: string }[];
}

interface LoreMediaItem {
    name: string;
    type: string;
    link: string | null;
}

interface LoreData {
    id: number;
    name: string;
    file?: string | null;
    media?: LoreMediaItem[];
    characters?: LoreCharacter[];
}

const props = defineProps<{
    lore: LoreData;
}>();

const characterCount = computed(() => props.lore.characters?.length ?? 0);
const singleCharacter = computed(() => (characterCount.value === 1 ? props.lore.characters![0] : null));
const mediaList = computed(() => props.lore.media ?? []);
const fileUrl = computed(() => (props.lore.file ? `/storage/${props.lore.file}` : null));
const isPdf = computed(() => props.lore.file?.endsWith('.pdf') ?? false);

const formatType = (type: string) => {
    return type ? type.replace(/_/g, ' ') : '';
};
</script>

<template>
    <Card class="flex flex-col overflow-hidden">
        <!-- Header: story name -->
        <div class="flex items-center gap-2 border-b bg-secondary px-3 py-2">
            <BookOpen class="h-4 w-4 shrink-0 text-muted-foreground" />
            <h3 class="text-sm font-semibold leading-tight">{{ lore.name }}</h3>
        </div>

        <!-- File attachment -->
        <div v-if="fileUrl" class="border-b px-3 py-2">
            <a v-if="isPdf" :href="fileUrl" target="_blank" class="flex items-center gap-1.5 text-xs text-primary hover:underline">
                <FileText class="h-3.5 w-3.5 shrink-0" />
                View PDF
                <ExternalLink class="h-3 w-3" />
            </a>
            <a v-else :href="fileUrl" target="_blank">
                <img :src="fileUrl" :alt="lore.name" class="max-h-32 w-full rounded object-contain" loading="lazy" />
            </a>
        </div>

        <!-- Body: media sources -->
        <div class="flex-1 px-3 py-2.5">
            <div v-if="mediaList.length" class="space-y-1.5">
                <div v-for="media in mediaList" :key="media.name" class="flex items-start gap-1.5">
                    <Library class="mt-0.5 h-3 w-3 shrink-0 text-muted-foreground" />
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1">
                            <a v-if="media.link" :href="media.link" target="_blank" class="text-xs text-primary hover:underline">
                                {{ media.name }}
                                <ExternalLink class="inline h-3 w-3" />
                            </a>
                            <span v-else class="text-xs text-muted-foreground">{{ media.name }}</span>
                            <Badge v-if="media.type" variant="outline" class="text-[10px] capitalize">
                                {{ formatType(media.type) }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="text-xs text-muted-foreground">No media sources</p>
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
                    <SheetTitle class="text-lg font-semibold">{{ lore.name }}</SheetTitle>
                    <SheetDescription v-if="mediaList.length" class="space-y-0.5 text-sm text-muted-foreground">
                        <div v-for="media in mediaList" :key="media.name" class="flex items-center gap-1">
                            {{ media.name }}
                            <Badge v-if="media.type" variant="outline" class="ml-1 text-[10px] capitalize">
                                {{ formatType(media.type) }}
                            </Badge>
                        </div>
                    </SheetDescription>

                    <Separator class="my-4" />

                    <div class="space-y-1">
                        <Link
                            v-for="character in lore.characters"
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

                    <template v-if="mediaList.some((m) => m.link)">
                        <Separator class="my-4" />
                        <div class="space-y-1">
                            <a
                                v-for="media in mediaList.filter((m) => m.link)"
                                :key="media.name"
                                :href="media.link!"
                                target="_blank"
                                class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline"
                            >
                                <ExternalLink class="h-3.5 w-3.5" />
                                {{ media.name }}
                            </a>
                        </div>
                    </template>
                </SheetContent>
            </Sheet>

            <span v-else class="text-muted-foreground">No linked characters</span>
        </div>
    </Card>
</template>
