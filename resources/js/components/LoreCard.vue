<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { Link } from '@inertiajs/vue3';
import { BookOpen, ExternalLink, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface LoreCharacter {
    display_name: string;
    slug: string;
    standard_miniatures?: { id: number; slug: string }[];
}

interface LoreMedia {
    name: string;
    type: string;
    link: string | null;
}

interface LoreData {
    id: number;
    name: string;
    media?: LoreMedia | null;
    characters?: LoreCharacter[];
}

const props = defineProps<{
    lore: LoreData;
}>();

const characterCount = computed(() => props.lore.characters?.length ?? 0);
const singleCharacter = computed(() => (characterCount.value === 1 ? props.lore.characters![0] : null));

const formatType = (type: string) => {
    return type ? type.replace(/_/g, ' ') : '';
};
</script>

<template>
    <Card class="flex flex-col overflow-hidden">
        <div class="flex items-center border-b bg-secondary px-3 py-1.5">
            <Badge v-if="lore.media?.type" variant="outline" class="text-[10px] capitalize">
                {{ formatType(lore.media.type) }}
            </Badge>
        </div>
        <div class="flex-1 px-3 py-2.5">
            <div class="flex items-start gap-2">
                <BookOpen class="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold leading-tight">{{ lore.name }}</h3>
                    <a
                        v-if="lore.media?.link"
                        :href="lore.media.link"
                        target="_blank"
                        class="mt-0.5 inline-flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                        {{ lore.media.name }}
                        <ExternalLink class="h-3 w-3" />
                    </a>
                    <p v-else-if="lore.media" class="mt-0.5 text-xs text-muted-foreground">{{ lore.media.name }}</p>
                </div>
            </div>
        </div>

        <!-- Footer: single character = direct link, multiple = sheet trigger, none = placeholder -->
        <div class="flex items-center gap-1.5 border-t px-3 py-1.5 text-xs">
            <Users class="h-3 w-3 shrink-0 text-muted-foreground" />

            <!-- Single character: direct link -->
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

            <!-- Multiple characters: open sheet -->
            <Sheet v-else-if="characterCount > 1">
                <SheetTrigger as-child>
                    <button class="cursor-pointer text-primary hover:underline">{{ characterCount }} Characters</button>
                </SheetTrigger>
                <SheetContent side="right" class="overflow-y-auto">
                    <SheetTitle class="text-lg font-semibold">{{ lore.name }}</SheetTitle>
                    <SheetDescription v-if="lore.media" class="text-sm text-muted-foreground">
                        {{ lore.media.name }}
                        <Badge v-if="lore.media.type" variant="outline" class="ml-1.5 text-[10px] capitalize">
                            {{ formatType(lore.media.type) }}
                        </Badge>
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

                    <template v-if="lore.media?.link">
                        <Separator class="my-4" />
                        <a
                            :href="lore.media.link"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 text-sm text-primary hover:underline"
                        >
                            <ExternalLink class="h-3.5 w-3.5" />
                            View Source
                        </a>
                    </template>
                </SheetContent>
            </Sheet>

            <!-- No characters -->
            <span v-else class="text-muted-foreground">No linked characters</span>
        </div>
    </Card>
</template>
