<script setup lang="ts">
import GameText from '@/components/GameText.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { factionBackground } from '@/composables/useFactionColor';
import { ArrowUpCircle, Loader2, MapPin, Plus, Puzzle, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    type: string | null;
}

interface ReferenceCharacter {
    id: number;
    display_name: string;
    slug: string;
    faction: string;
    type: string;
    front_image: string | null;
    back_image: string | null;
}

interface ReferenceData {
    markers: ReferenceMarker[];
    tokens: ReferenceToken[];
    upgrades: ReferenceUpgrade[];
    characters: ReferenceCharacter[];
}

const props = withDefaults(
    defineProps<{
        references: ReferenceData | null;
        loading: boolean;
        compact?: boolean;
        editable?: boolean;
    }>(),
    { compact: false, editable: false },
);

const emit = defineEmits<{
    'add-reference': [type: 'characters' | 'upgrades' | 'markers' | 'tokens', item: any];
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

// ─── Add Item Dialog ───
const addDialogOpen = ref(false);
const addDialogType = ref<'characters' | 'upgrades' | 'markers' | 'tokens'>('characters');
const addSearch = ref('');
const addResults = ref<any[]>([]);
const addLoading = ref(false);
let addDebounce: ReturnType<typeof setTimeout>;

const addDialogTitle = computed(() => {
    const labels: Record<string, string> = { characters: 'Character', upgrades: 'Upgrade', markers: 'Marker', tokens: 'Token' };
    return 'Add ' + (labels[addDialogType.value] ?? 'Item');
});

const openAddDialog = (type: 'characters' | 'upgrades' | 'markers' | 'tokens') => {
    addDialogType.value = type;
    addSearch.value = '';
    addResults.value = [];
    addDialogOpen.value = true;
};

const existingIds = computed(() => {
    const refs = props.references;
    if (!refs) return new Set<number>();
    const list = refs[addDialogType.value] ?? [];
    return new Set(list.map((item: any) => item.id));
});

const searchAdd = (q: string) => {
    addSearch.value = q;
    clearTimeout(addDebounce);
    if (q.length < 2) {
        addResults.value = [];
        return;
    }
    addLoading.value = true;
    addDebounce = setTimeout(async () => {
        try {
            const type = addDialogType.value;
            let url: string;
            if (type === 'characters') {
                url = '/api/characters/search?q=' + encodeURIComponent(q);
            } else if (type === 'upgrades') {
                url = '/api/upgrades?name=' + encodeURIComponent(q);
            } else if (type === 'markers') {
                url = '/api/markers?name=' + encodeURIComponent(q);
            } else {
                url = '/api/tokens?name=' + encodeURIComponent(q);
            }
            const res = await fetch(url);
            const data = await res.json();
            addResults.value = Array.isArray(data) ? data : (data.data ?? []);
        } catch {
            addResults.value = [];
        }
        addLoading.value = false;
    }, 300);
};

const selectAddItem = (item: any) => {
    emit('add-reference', addDialogType.value, item);
    addDialogOpen.value = false;
    addSearch.value = '';
    addResults.value = [];
};

const itemDisplayName = (item: any): string => item.display_name ?? item.name ?? '';
</script>

<template>
    <div :class="compact ? 'p-2' : 'mt-3'">
        <template v-if="!compact">
            <Separator class="mb-3" />
            <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">References</span>
                <Loader2 v-if="loading" class="size-3.5 animate-spin text-muted-foreground" />
            </div>
        </template>
        <div v-else-if="loading" class="flex justify-center py-3">
            <Loader2 class="size-4 animate-spin text-muted-foreground" />
        </div>

        <div v-if="!references && !loading" class="py-3 text-center text-xs text-muted-foreground">No references found</div>

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
                        :class="factionBackground(char.faction)"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-white transition-opacity hover:opacity-80"
                        @click="openCharacter(char)"
                    >
                        <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ char.display_name }}</span>
                    </button>
                </div>
                <Button
                    v-if="editable"
                    variant="ghost"
                    size="sm"
                    class="mt-1 w-full gap-1 text-xs text-muted-foreground"
                    @click="openAddDialog('characters')"
                >
                    <Plus class="size-3" /> Add Character
                </Button>
            </TabsContent>

            <TabsContent value="upgrades">
                <div v-if="!references?.upgrades.length" class="py-4 text-center text-xs text-muted-foreground">No upgrades</div>
                <div v-else class="max-h-[30vh] space-y-0.5 overflow-y-auto">
                    <button
                        v-for="upgrade in references.upgrades"
                        :key="upgrade.id"
                        class="flex w-full items-center gap-2 rounded-md border px-2 py-1.5 text-left text-sm transition-colors hover:bg-accent"
                        @click="openUpgrade(upgrade)"
                    >
                        <span class="min-w-0 flex-1 truncate text-xs font-medium">{{ upgrade.name }}</span>
                        <Badge v-if="upgrade.type" variant="outline" class="shrink-0 px-1 py-0 text-[9px]">{{ upgrade.type }}</Badge>
                    </button>
                </div>
                <Button
                    v-if="editable"
                    variant="ghost"
                    size="sm"
                    class="mt-1 w-full gap-1 text-xs text-muted-foreground"
                    @click="openAddDialog('upgrades')"
                >
                    <Plus class="size-3" /> Add Upgrade
                </Button>
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
                <Button
                    v-if="editable"
                    variant="ghost"
                    size="sm"
                    class="mt-1 w-full gap-1 text-xs text-muted-foreground"
                    @click="openAddDialog('markers')"
                >
                    <Plus class="size-3" /> Add Marker
                </Button>
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
                <Button
                    v-if="editable"
                    variant="ghost"
                    size="sm"
                    class="mt-1 w-full gap-1 text-xs text-muted-foreground"
                    @click="openAddDialog('tokens')"
                >
                    <Plus class="size-3" /> Add Token
                </Button>
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
                        class="flex min-h-0 flex-1 items-start justify-center [&_img]:max-h-[55dvh] [&_img]:w-auto [&_img]:object-contain"
                    >
                        <UpgradeFlipCard
                            :front-image="activeCharacter.front_image"
                            :back-image="activeCharacter.back_image"
                            :alt-text="activeCharacter.display_name"
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

    <!-- Upgrade Drawer -->
    <Drawer v-model:open="upgradeDrawerOpen">
        <DrawerContent>
            <div v-if="activeUpgrade" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ activeUpgrade.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">Upgrade</div>
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
                    <p class="text-sm leading-relaxed"><GameText :text="textDrawerDescription" /></p>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Add Item Dialog -->
    <Dialog
        v-model:open="addDialogOpen"
        @update:open="
            (open) => {
                if (!open) {
                    addSearch = '';
                    addResults = [];
                }
            }
        "
    >
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>{{ addDialogTitle }}</DialogTitle>
            </DialogHeader>
            <Input :model-value="addSearch" placeholder="Search..." @update:model-value="searchAdd($event as string)" />
            <div class="max-h-48 space-y-0.5 overflow-y-auto">
                <div v-if="addLoading" class="flex justify-center py-3">
                    <Loader2 class="size-4 animate-spin text-muted-foreground" />
                </div>
                <template v-else-if="addResults.length">
                    <button
                        v-for="item in addResults"
                        :key="item.id"
                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors"
                        :class="[
                            addDialogType === 'characters' && item.faction ? factionBackground(item.faction) + ' text-white' : '',
                            existingIds.has(item.id)
                                ? 'cursor-not-allowed opacity-40'
                                : addDialogType === 'characters' && item.faction
                                  ? 'hover:opacity-80'
                                  : 'hover:bg-accent',
                        ]"
                        :disabled="existingIds.has(item.id)"
                        @click="selectAddItem(item)"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-medium">{{ itemDisplayName(item) }}</div>
                            <div
                                v-if="addDialogType === 'characters'"
                                class="flex items-center gap-1.5 text-[10px]"
                                :class="item.faction ? 'text-white/70' : 'text-muted-foreground'"
                            >
                                <span v-if="item.station" class="capitalize">{{ item.station }}</span>
                                <span v-if="item.miniatures?.length > 1">&middot; {{ item.miniatures.length }} sculpts</span>
                            </div>
                        </div>
                        <Badge v-if="existingIds.has(item.id)" variant="outline" class="shrink-0 border-white/30 px-1 py-0 text-[9px]">Added</Badge>
                    </button>
                </template>
                <div v-else-if="addSearch.length >= 2" class="py-3 text-center text-xs text-muted-foreground">No results found</div>
                <div v-else class="py-3 text-center text-xs text-muted-foreground">Type at least 2 characters to search</div>
            </div>
            <DialogFooter>
                <Button variant="outline" class="w-full" @click="addDialogOpen = false">Cancel</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
