<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { ArrowUpCircle, Loader2, MapPin, Puzzle, Users } from 'lucide-vue-next';
import { ref } from 'vue';

interface ReferenceMarker {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    base: string | null;
}

interface ReferenceToken {
    id: number;
    name: string;
    slug: string;
    description: string | null;
}

interface ReferenceUpgrade {
    id: number;
    name: string;
    slug: string;
    front_image: string | null;
    back_image: string | null;
}

interface ReferenceCharacter {
    id: number;
    display_name: string;
    slug: string;
    faction: string;
    type: string;
    front_image: string | null;
}

interface ReferenceData {
    markers: ReferenceMarker[];
    tokens: ReferenceToken[];
    upgrades: ReferenceUpgrade[];
    characters: ReferenceCharacter[];
}

defineProps<{
    references: ReferenceData | null;
    loading: boolean;
}>();

// ─── Drawers ───
const characterDrawerOpen = ref(false);
const activeCharacter = ref<ReferenceCharacter | null>(null);

const upgradeDrawerOpen = ref(false);
const activeUpgrade = ref<ReferenceUpgrade | null>(null);

const textDrawerOpen = ref(false);
const textDrawerTitle = ref('');
const textDrawerLabel = ref('');
const textDrawerDescription = ref('');
const textDrawerSubtitle = ref('');

const openCharacter = (char: ReferenceCharacter) => {
    activeCharacter.value = char;
    characterDrawerOpen.value = true;
};

const openUpgrade = (upgrade: ReferenceUpgrade) => {
    activeUpgrade.value = upgrade;
    upgradeDrawerOpen.value = true;
};

const openTextDrawer = (name: string, label: string, description: string | null, subtitle?: string) => {
    textDrawerTitle.value = name;
    textDrawerLabel.value = label;
    textDrawerDescription.value = description || 'No description available.';
    textDrawerSubtitle.value = subtitle || '';
    textDrawerOpen.value = true;
};

const activeTab = ref('characters');
</script>

<template>
    <div class="mt-3">
        <Separator class="mb-3" />
        <div class="mb-2 flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">References</span>
            <Loader2 v-if="loading" class="size-3.5 animate-spin text-muted-foreground" />
        </div>

        <div v-if="!references && !loading" class="py-4 text-center text-xs text-muted-foreground">No references found</div>

        <Tabs v-else v-model="activeTab" class="w-full">
            <TabsList class="mb-2 grid w-full grid-cols-4">
                <TabsTrigger value="characters" class="gap-1">
                    <Users class="size-3.5" />
                    <Badge v-if="references?.characters.length" variant="secondary" class="px-1 py-0 text-[9px]">
                        {{ references.characters.length }}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="upgrades" class="gap-1">
                    <ArrowUpCircle class="size-3.5" />
                    <Badge v-if="references?.upgrades.length" variant="secondary" class="px-1 py-0 text-[9px]">
                        {{ references.upgrades.length }}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="markers" class="gap-1">
                    <MapPin class="size-3.5" />
                    <Badge v-if="references?.markers.length" variant="secondary" class="px-1 py-0 text-[9px]">
                        {{ references.markers.length }}
                    </Badge>
                </TabsTrigger>
                <TabsTrigger value="tokens" class="gap-1">
                    <Puzzle class="size-3.5" />
                    <Badge v-if="references?.tokens.length" variant="secondary" class="px-1 py-0 text-[9px]">
                        {{ references.tokens.length }}
                    </Badge>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="characters">
                <div v-if="!references?.characters.length" class="py-4 text-center text-xs text-muted-foreground">No linked characters</div>
                <div v-else class="max-h-[30vh] space-y-0.5 overflow-y-auto">
                    <button
                        v-for="char in references.characters"
                        :key="char.id"
                        class="flex w-full items-center gap-2 rounded-md border px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent"
                        @click="openCharacter(char)"
                    >
                        <FactionLogo :faction="char.faction" class-name="size-4 shrink-0" />
                        <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ char.display_name }}</span>
                        <Badge variant="outline" class="shrink-0 px-1 py-0 text-[9px]">{{ char.type }}</Badge>
                    </button>
                </div>
            </TabsContent>

            <TabsContent value="upgrades">
                <div v-if="!references?.upgrades.length" class="py-4 text-center text-xs text-muted-foreground">No character upgrades</div>
                <div v-else class="max-h-[30vh] space-y-0.5 overflow-y-auto">
                    <button
                        v-for="upgrade in references.upgrades"
                        :key="upgrade.id"
                        class="flex w-full items-center gap-2 rounded-md border px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent"
                        @click="openUpgrade(upgrade)"
                    >
                        <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ upgrade.name }}</span>
                    </button>
                </div>
            </TabsContent>

            <TabsContent value="markers">
                <div v-if="!references?.markers.length" class="py-4 text-center text-xs text-muted-foreground">No markers</div>
                <div v-else class="max-h-[30vh] space-y-0.5 overflow-y-auto">
                    <button
                        v-for="marker in references.markers"
                        :key="marker.id"
                        class="flex w-full items-center gap-2 rounded-md border px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent"
                        @click="openTextDrawer(marker.name, 'Marker', marker.description, marker.base ? marker.base + 'mm base' : undefined)"
                    >
                        <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ marker.name }}</span>
                        <Badge variant="outline" class="shrink-0 px-1 py-0 text-[9px]">Marker</Badge>
                    </button>
                </div>
            </TabsContent>

            <TabsContent value="tokens">
                <div v-if="!references?.tokens.length" class="py-4 text-center text-xs text-muted-foreground">No tokens</div>
                <div v-else class="max-h-[30vh] space-y-0.5 overflow-y-auto">
                    <button
                        v-for="token in references.tokens"
                        :key="token.id"
                        class="flex w-full items-center gap-2 rounded-md border px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent"
                        @click="openTextDrawer(token.name, 'Token', token.description)"
                    >
                        <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ token.name }}</span>
                        <Badge variant="outline" class="shrink-0 px-1 py-0 text-[9px]">Token</Badge>
                    </button>
                </div>
            </TabsContent>
        </Tabs>
    </div>

    <!-- Character Drawer -->
    <Drawer v-model:open="characterDrawerOpen">
        <DrawerContent>
            <div v-if="activeCharacter" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ activeCharacter.display_name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">{{ activeCharacter.type }}</div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <div
                        v-if="activeCharacter.front_image"
                        class="flex min-h-0 flex-1 items-start justify-center"
                    >
                        <img
                            :src="'/storage/' + activeCharacter.front_image"
                            :alt="activeCharacter.display_name"
                            loading="lazy"
                            decoding="async"
                            class="max-h-[55dvh] w-auto rounded-lg"
                        />
                    </div>
                    <div v-else class="py-8 text-center text-sm text-muted-foreground">No card image available</div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Upgrade Drawer -->
    <Drawer v-model:open="upgradeDrawerOpen">
        <DrawerContent>
            <div v-if="activeUpgrade" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ activeUpgrade.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Character Upgrade</div>
                </DrawerHeader>
                <div class="flex min-h-0 flex-1 flex-col px-4 pb-2">
                    <div
                        v-if="activeUpgrade.front_image"
                        class="flex min-h-0 flex-1 items-start justify-center [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain"
                    >
                        <UpgradeFlipCard
                            :front-image="activeUpgrade.front_image"
                            :back-image="activeUpgrade.back_image"
                            :alt-text="activeUpgrade.name"
                            :upgrade-slug="activeUpgrade.slug"
                            :show-link="false"
                        />
                    </div>
                    <div v-else class="py-8 text-center text-sm text-muted-foreground">No card image available</div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Text Drawer (Markers / Tokens) -->
    <Drawer v-model:open="textDrawerOpen">
        <DrawerContent>
            <div class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ textDrawerTitle }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">
                        {{ textDrawerLabel }}<span v-if="textDrawerSubtitle"> &middot; {{ textDrawerSubtitle }}</span>
                    </div>
                </DrawerHeader>
                <div class="px-4 pb-4">
                    <p class="text-sm leading-relaxed">{{ textDrawerDescription }}</p>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
