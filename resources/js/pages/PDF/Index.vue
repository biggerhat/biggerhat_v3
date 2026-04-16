<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import GameIcon from '@/components/GameIcon.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField, NumberFieldContent, NumberFieldDecrement, NumberFieldIncrement, NumberFieldInput } from '@/components/ui/number-field';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import UpgradeCardView from '@/components/UpgradeCardView.vue';
import { useVirtualizer } from '@tanstack/vue-virtual';
import { refDebounced } from '@vueuse/core';
import { ArrowUpFromLine, Check, ChevronsUpDown, CircleX, EllipsisVertical, Search, SquareMinus, SquarePlus, UserPlus } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    upgrades: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    factions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    keywords: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

// State
const activeTab = ref('characters');
const pdfCards = ref<any[]>([]);
const filterText = ref('');
const filterUpgradeText = ref('');
const debouncedFilterText = refDebounced(filterText, 150);
const debouncedUpgradeFilterText = refDebounced(filterUpgradeText, 150);
const selectedKeyword = ref<any>(null);
const selectedFaction = ref<string | null>(null);
const totalStones = ref(50);
const separateImages = ref(false);

// Preview drawer
const drawerOpen = ref(false);
const previewItem = ref<any>(null);
const previewType = ref<'character' | 'upgrade'>('character');

const openPreview = (item: any, type: 'character' | 'upgrade') => {
    previewItem.value = item;
    previewType.value = type;
    drawerOpen.value = true;
};

// Computed
const stones = computed(() => pdfCards.value.reduce((sum, card) => sum + (card.card_type === 'miniature' ? card.cost : 0), 0));
const cache = computed(() => {
    const remaining = totalStones.value - stones.value;
    return remaining > 6 ? 6 : Math.max(0, remaining);
});

// Character filtering
const results = computed(() => {
    let filtered = props.characters as any[];
    if (selectedFaction.value) {
        filtered = filtered.filter((c) => c.faction === selectedFaction.value);
    }
    if (selectedKeyword.value) {
        filtered = filtered.filter((c) => c.keywords.some((k: any) => k.slug === selectedKeyword.value.slug));
    }
    const filter = debouncedFilterText.value.toLowerCase();
    if (filter) {
        filtered = filtered.filter((c) => c.display_name.toLowerCase().includes(filter));
    }
    return filtered;
});

// Upgrade filtering
const upgradeResults = computed(() => {
    let filtered = props.upgrades as any[];
    if (selectedFaction.value) {
        filtered = filtered.filter((u) => u.faction === selectedFaction.value);
    }
    const filter = debouncedUpgradeFilterText.value.toLowerCase();
    if (filter) {
        filtered = filtered.filter((u) => u.name.toLowerCase().includes(filter));
    }
    return filtered;
});

// Virtual scrollers
const characterScrollRef = ref<HTMLElement | null>(null);
const upgradeScrollRef = ref<HTMLElement | null>(null);

const characterVirtualizer = useVirtualizer(
    computed(() => ({
        count: results.value.length,
        getScrollElement: () => characterScrollRef.value,
        estimateSize: () => 56,
        overscan: 10,
    })),
);

const upgradeVirtualizer = useVirtualizer(
    computed(() => ({
        count: upgradeResults.value.length,
        getScrollElement: () => upgradeScrollRef.value,
        estimateSize: () => 48,
        overscan: 10,
    })),
);

// Reset scroll on filter changes
watch([debouncedFilterText, selectedFaction, selectedKeyword], () => {
    characterScrollRef.value?.scrollTo(0, 0);
});
watch([debouncedUpgradeFilterText, selectedFaction], () => {
    upgradeScrollRef.value?.scrollTo(0, 0);
});

// Actions
const filterFaction = (factionSlug: string) => {
    selectedFaction.value = selectedFaction.value === factionSlug ? null : factionSlug;
};

const add = (character: any) => {
    pdfCards.value.push(character);
    if (character.crew_upgrades?.length) {
        character.crew_upgrades.forEach((u: any) => addUpgrade(u));
    }
    if (character.totem_name) {
        (props.characters as any[]).filter((c) => c.slug === character.totem_name).forEach((c) => pdfCards.value.push(c));
    }
};

const addUpgrade = (upgrade: any) => {
    pdfCards.value.push(upgrade);
};

const remove = (index: number) => {
    pdfCards.value.splice(index, 1);
};

const clear = () => {
    pdfCards.value = [];
};

const generatePDF = () => {
    const pdfValues = pdfCards.value.map((card) => ({
        card_type: card.card_type,
        id: card.card_type === 'miniature' ? card.standard_miniatures[0].id : card.id,
    }));
    const options = { separate_images: separateImages.value };
    window.open(route('tools.pdf.download', { cards: btoa(JSON.stringify(pdfValues)), options: btoa(JSON.stringify(options)) }), '_blank');
};

// URL params on mount
onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('faction')) {
        filterFaction(urlParams.get('faction')!);
    }
    if (urlParams.get('keyword')) {
        const found = (props.keywords as any[]).find((k) => k.slug === urlParams.get('keyword'));
        if (found) selectedKeyword.value = found;
    }
});
</script>

<template>
    <Head title="PDF Generator" />

    <div class="relative pb-12">
        <!-- Gradient glow -->
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="PDF Generator">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">Build and download PDF cards for your crew.</div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 sm:px-4 lg:px-6">
            <Tabs v-model="activeTab" default-value="characters">
                <TabsList class="w-full md:w-auto">
                    <TabsTrigger value="characters" class="flex-1 gap-1.5 md:flex-none">
                        <UserPlus class="size-4" />
                        <span class="hidden sm:inline">Characters</span>
                    </TabsTrigger>
                    <TabsTrigger value="upgrades" class="flex-1 gap-1.5 md:flex-none">
                        <ArrowUpFromLine class="size-4" />
                        <span class="hidden sm:inline">Upgrades</span>
                    </TabsTrigger>
                    <TabsTrigger value="list" class="flex-1 gap-1.5 md:hidden"> List ({{ pdfCards.length }}) </TabsTrigger>
                </TabsList>

                <!-- Shared faction filter -->
                <div v-show="activeTab !== 'list'" class="mt-3 flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="faction in factions"
                        :key="faction.slug"
                        @click="filterFaction(faction.slug)"
                        class="flex items-center gap-1.5 rounded-full border-2 px-2.5 py-1 text-xs font-medium transition-all duration-200"
                        :class="[
                            selectedFaction === faction.slug
                                ? 'shadow-sm'
                                : selectedFaction
                                  ? 'border-transparent opacity-40 grayscale hover:opacity-70 hover:grayscale-0'
                                  : 'border-transparent hover:bg-accent',
                        ]"
                        :style="
                            selectedFaction === faction.slug
                                ? {
                                      borderColor: `hsl(var(--${faction.color}))`,
                                      backgroundColor: `hsl(var(--${faction.color}) / 0.1)`,
                                  }
                                : {}
                        "
                    >
                        <img :src="faction.logo" :alt="faction.name" class="size-5" />
                        <span class="hidden md:inline">{{ faction.name }}</span>
                    </button>
                    <button
                        v-if="selectedFaction"
                        @click="selectedFaction = null"
                        class="ml-0.5 rounded-full p-1 text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                    >
                        <CircleX class="size-4" />
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-6 gap-4">
                    <!-- Characters Tab -->
                    <TabsContent value="characters" class="col-span-6 mt-0 md:col-span-3">
                        <Card>
                            <CardContent class="p-2 md:p-3">
                                <!-- Filters -->
                                <div class="mb-3 grid gap-2 md:grid-cols-2">
                                    <div class="flex items-center">
                                        <Combobox v-model="selectedKeyword" by="label">
                                            <ComboboxAnchor as-child>
                                                <ComboboxTrigger as-child>
                                                    <Button variant="outline" class="w-full justify-between">
                                                        <span class="truncate">{{ selectedKeyword?.name ?? 'Select Keyword' }}</span>
                                                        <ChevronsUpDown class="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                                    </Button>
                                                </ComboboxTrigger>
                                            </ComboboxAnchor>
                                            <ComboboxList class="max-h-80 overflow-y-auto">
                                                <div class="relative w-full items-center">
                                                    <ComboboxInput
                                                        class="h-10 rounded-none border-0 border-b pl-9 focus-visible:ring-0"
                                                        placeholder="Select Keyword..."
                                                    />
                                                    <span class="absolute inset-y-0 start-0 flex items-center justify-center px-3">
                                                        <Search class="size-4 text-muted-foreground" />
                                                    </span>
                                                </div>
                                                <ComboboxEmpty>No Keyword Found.</ComboboxEmpty>
                                                <ComboboxGroup>
                                                    <ComboboxItem v-for="keyword in props.keywords" :key="keyword.slug" :value="keyword">
                                                        {{ keyword.name }}
                                                        <Check v-if="keyword.slug === selectedKeyword?.slug" class="ml-auto h-4 w-4" />
                                                    </ComboboxItem>
                                                </ComboboxGroup>
                                            </ComboboxList>
                                        </Combobox>
                                        <CircleX
                                            v-if="selectedKeyword"
                                            class="my-auto ml-2 shrink-0 cursor-pointer text-destructive"
                                            @click="selectedKeyword = null"
                                        />
                                    </div>
                                    <div class="flex items-center">
                                        <Input v-model="filterText" placeholder="Filter Characters" />
                                        <CircleX
                                            v-if="filterText"
                                            class="my-auto ml-2 shrink-0 cursor-pointer text-destructive"
                                            @click="filterText = ''"
                                        />
                                    </div>
                                </div>

                                <div class="mb-2 text-xs text-muted-foreground">{{ results.length }} results</div>

                                <!-- Virtual character list -->
                                <div ref="characterScrollRef" class="h-[50vh] overflow-y-auto md:h-[calc(100vh-20rem)]">
                                    <div
                                        :style="{
                                            height: `${characterVirtualizer.getTotalSize()}px`,
                                            position: 'relative',
                                            width: '100%',
                                        }"
                                    >
                                        <div
                                            v-for="virtualRow in characterVirtualizer.getVirtualItems()"
                                            :key="virtualRow.key"
                                            :data-index="virtualRow.index"
                                            :ref="
                                                (el) => {
                                                    if (el) characterVirtualizer.measureElement(el as Element);
                                                }
                                            "
                                            :style="{
                                                position: 'absolute',
                                                top: 0,
                                                left: 0,
                                                width: '100%',
                                                transform: `translateY(${virtualRow.start}px)`,
                                            }"
                                        >
                                            <!-- Character row -->
                                            <div
                                                :class="factionBackground(results[virtualRow.index].faction)"
                                                class="my-0.5 flex items-center justify-between rounded-md border border-border/50 px-2 py-1.5 transition-colors hover:brightness-[0.93] dark:hover:brightness-110"
                                            >
                                                <div
                                                    class="min-w-0 flex-1 cursor-pointer"
                                                    @click="openPreview(results[virtualRow.index], 'character')"
                                                >
                                                    <div class="text-sm font-semibold">
                                                        {{ results[virtualRow.index].display_name }}
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                                        <span v-if="results[virtualRow.index].cost">{{ results[virtualRow.index].cost }}ss</span>
                                                        <Badge
                                                            v-if="results[virtualRow.index].station"
                                                            variant="secondary"
                                                            class="px-1 py-0 text-[10px] capitalize"
                                                        >
                                                            {{ results[virtualRow.index].station }}
                                                            <span v-if="results[virtualRow.index].count > 1">
                                                                ({{ results[virtualRow.index].count }})
                                                            </span>
                                                        </Badge>
                                                        <span
                                                            v-if="results[virtualRow.index].keywords?.length"
                                                            class="truncate text-muted-foreground/70"
                                                        >
                                                            {{ results[virtualRow.index].keywords.map((k: any) => k.name).join(', ') }}
                                                        </span>
                                                    </div>
                                                </div>
                                                <Button variant="ghost" size="icon" class="size-7 shrink-0" @click="add(results[virtualRow.index])">
                                                    <SquarePlus class="size-4" />
                                                </Button>
                                            </div>

                                            <!-- Crew upgrades -->
                                            <div v-if="results[virtualRow.index].crew_upgrades?.length">
                                                <div
                                                    v-for="upgrade in results[virtualRow.index].crew_upgrades"
                                                    :key="upgrade.id"
                                                    class="my-0.5 flex items-center"
                                                >
                                                    <ArrowUpFromLine class="mx-1 size-3.5 shrink-0 text-muted-foreground" />
                                                    <div
                                                        :class="factionBackground(upgrade.faction)"
                                                        class="flex flex-1 items-center justify-between rounded-md border border-border/50 px-2 py-1.5 transition-colors hover:brightness-[0.93] dark:hover:brightness-110"
                                                    >
                                                        <div
                                                            role="button"
                                                            tabindex="0"
                                                            class="min-w-0 flex-1 cursor-pointer"
                                                            @click="openPreview(upgrade, 'upgrade')"
                                                            @keydown.enter="openPreview(upgrade, 'upgrade')"
                                                        >
                                                            <div class="text-sm font-semibold">{{ upgrade.name }}</div>
                                                            <div class="text-xs text-muted-foreground">
                                                                <span v-if="upgrade.type">
                                                                    {{ upgrade.type }}
                                                                    <span v-if="upgrade.master"> &mdash; {{ upgrade.master }} </span>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <Button variant="ghost" size="icon" class="size-7 shrink-0" @click="addUpgrade(upgrade)">
                                                            <SquarePlus class="size-4" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- Upgrades Tab -->
                    <TabsContent value="upgrades" class="col-span-6 mt-0 md:col-span-3">
                        <Card>
                            <CardContent class="p-2 md:p-3">
                                <!-- Filter -->
                                <div class="mb-3 flex items-center">
                                    <Input v-model="filterUpgradeText" placeholder="Filter Upgrades" />
                                    <CircleX
                                        v-if="filterUpgradeText"
                                        class="my-auto ml-2 shrink-0 cursor-pointer text-destructive"
                                        @click="filterUpgradeText = ''"
                                    />
                                </div>

                                <div class="mb-2 text-xs text-muted-foreground">{{ upgradeResults.length }} results</div>

                                <!-- Virtual upgrade list -->
                                <div ref="upgradeScrollRef" class="h-[50vh] overflow-y-auto md:h-[calc(100vh-20rem)]">
                                    <div
                                        :style="{
                                            height: `${upgradeVirtualizer.getTotalSize()}px`,
                                            position: 'relative',
                                            width: '100%',
                                        }"
                                    >
                                        <div
                                            v-for="virtualRow in upgradeVirtualizer.getVirtualItems()"
                                            :key="virtualRow.key"
                                            :data-index="virtualRow.index"
                                            :ref="
                                                (el) => {
                                                    if (el) upgradeVirtualizer.measureElement(el as Element);
                                                }
                                            "
                                            :style="{
                                                position: 'absolute',
                                                top: 0,
                                                left: 0,
                                                width: '100%',
                                                transform: `translateY(${virtualRow.start}px)`,
                                            }"
                                        >
                                            <div
                                                :class="factionBackground(upgradeResults[virtualRow.index].faction)"
                                                class="my-0.5 flex items-center justify-between rounded-md border border-border/50 px-2 py-1.5 transition-colors hover:brightness-[0.93] dark:hover:brightness-110"
                                            >
                                                <div
                                                    class="min-w-0 flex-1 cursor-pointer"
                                                    @click="openPreview(upgradeResults[virtualRow.index], 'upgrade')"
                                                >
                                                    <div class="text-sm font-semibold">
                                                        {{ upgradeResults[virtualRow.index].name }}
                                                    </div>
                                                    <div class="text-xs text-muted-foreground">
                                                        <span v-if="upgradeResults[virtualRow.index].type">
                                                            {{ upgradeResults[virtualRow.index].type }}
                                                            <span v-if="upgradeResults[virtualRow.index].master">
                                                                &mdash; {{ upgradeResults[virtualRow.index].master }}
                                                            </span>
                                                            <span v-if="upgradeResults[virtualRow.index].count > 1">
                                                                ({{ upgradeResults[virtualRow.index].count }})
                                                            </span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-7 shrink-0"
                                                    @click="addUpgrade(upgradeResults[virtualRow.index])"
                                                >
                                                    <SquarePlus class="size-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <!-- List Panel -->
                    <div class="col-span-6 pb-20 md:col-span-3 md:pb-0" :class="activeTab === 'list' ? '' : 'hidden md:block'">
                        <Card>
                            <CardContent class="p-2 md:p-3">
                                <!-- Actions -->
                                <div class="mb-3 flex items-center justify-between gap-2">
                                    <div class="flex gap-2">
                                        <Button size="sm" variant="destructive" @click="clear()">Clear</Button>
                                        <Button size="sm" :disabled="pdfCards.length < 1" @click="generatePDF()">Generate PDF</Button>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="size-8">
                                                <EllipsisVertical class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent class="w-56" align="end">
                                            <DropdownMenuLabel>PDF Options</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuGroup>
                                                <DropdownMenuCheckboxItem v-model="separateImages"> Separate Images </DropdownMenuCheckboxItem>
                                            </DropdownMenuGroup>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>

                                <!-- Soulstone stats -->
                                <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-b pb-3 text-sm">
                                    <div class="flex items-center gap-1.5">
                                        <Label for="stone_count" class="whitespace-nowrap text-xs text-muted-foreground">Total</Label>
                                        <GameIcon type="soulstone" class-name="h-4 inline-block" />
                                        <NumberField id="stone_count" v-model="totalStones" :min="0" class="w-24">
                                            <NumberFieldContent>
                                                <NumberFieldDecrement />
                                                <NumberFieldInput />
                                                <NumberFieldIncrement />
                                            </NumberFieldContent>
                                        </NumberField>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-muted-foreground">Spent:</span>
                                        <span class="font-medium">{{ stones }}</span>
                                        <GameIcon type="soulstone" class-name="h-4 inline-block" />
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <span class="text-muted-foreground">Cache:</span>
                                        <span class="font-medium">{{ cache }}</span>
                                        <GameIcon type="soulstone" class-name="h-4 inline-block" />
                                    </div>
                                </div>

                                <!-- Selected cards list -->
                                <div class="max-h-[50vh] overflow-y-auto md:max-h-[calc(100vh-22rem)]">
                                    <div v-if="pdfCards.length === 0" class="py-8 text-center text-sm text-muted-foreground">
                                        Add characters and upgrades to your list
                                    </div>
                                    <div v-for="(card, index) in pdfCards" :key="index">
                                        <!-- Miniature card -->
                                        <div
                                            v-if="card.card_type === 'miniature'"
                                            :class="factionBackground(card.faction)"
                                            class="my-0.5 flex items-center justify-between rounded-md border border-border/50 px-2 py-1.5 transition-colors hover:brightness-[0.93] dark:hover:brightness-110"
                                        >
                                            <div
                                                role="button"
                                                tabindex="0"
                                                class="min-w-0 flex-1 cursor-pointer"
                                                @click="openPreview(card, 'character')"
                                                @keydown.enter="openPreview(card, 'character')"
                                            >
                                                <div class="text-sm font-semibold">{{ card.display_name }}</div>
                                                <div class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                                    <span v-if="card.cost">{{ card.cost }}ss</span>
                                                    <Badge v-if="card.station" variant="secondary" class="px-1 py-0 text-[10px] capitalize">
                                                        {{ card.station }}
                                                    </Badge>
                                                </div>
                                            </div>
                                            <Button variant="ghost" size="icon" class="size-7 shrink-0" @click="remove(index)">
                                                <SquareMinus class="size-4 text-destructive" />
                                            </Button>
                                        </div>

                                        <!-- Upgrade card -->
                                        <div v-if="card.card_type === 'upgrade'" class="my-0.5 flex items-center">
                                            <ArrowUpFromLine class="mx-1 size-3.5 shrink-0 text-muted-foreground" />
                                            <div
                                                :class="factionBackground(card.faction)"
                                                class="flex flex-1 items-center justify-between rounded-md border border-border/50 px-2 py-1.5 transition-colors hover:brightness-[0.93] dark:hover:brightness-110"
                                            >
                                                <div
                                                    role="button"
                                                    tabindex="0"
                                                    class="min-w-0 flex-1 cursor-pointer"
                                                    @click="openPreview(card, 'upgrade')"
                                                    @keydown.enter="openPreview(card, 'upgrade')"
                                                >
                                                    <div class="text-sm font-semibold">{{ card.name }}</div>
                                                    <div class="text-xs text-muted-foreground">
                                                        <span v-if="card.type">
                                                            {{ card.type }}
                                                            <span v-if="card.master"> &mdash; {{ card.master }} </span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <Button variant="ghost" size="icon" class="size-7 shrink-0" @click="remove(index)">
                                                    <SquareMinus class="size-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </Tabs>
        </div>

        <!-- Mobile sticky bottom bar -->
        <div v-if="activeTab === 'list'" class="fixed inset-x-0 bottom-0 z-10 border-t bg-background/95 p-3 backdrop-blur-sm md:hidden">
            <div class="container mx-auto flex items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-sm">
                    <span class="font-medium">{{ pdfCards.length }} cards</span>
                    <span class="text-muted-foreground">
                        &middot; {{ stones }}
                        <GameIcon type="soulstone" class-name="h-3.5 inline-block" />
                    </span>
                </div>
                <Button :disabled="pdfCards.length < 1" @click="generatePDF()">Generate PDF</Button>
            </div>
        </div>
    </div>

    <!-- Shared preview drawer -->
    <Drawer v-model:open="drawerOpen">
        <DrawerContent>
            <div class="mx-auto w-full max-w-sm">
                <DrawerHeader>
                    <DrawerTitle>
                        {{ previewType === 'character' ? previewItem?.display_name : previewItem?.name }}
                    </DrawerTitle>
                </DrawerHeader>
                <div class="p-4 pb-0">
                    <CharacterCardView
                        v-if="previewType === 'character' && previewItem"
                        :miniature="previewItem.standard_miniatures[0]"
                        show-link="false"
                        :character-slug="previewItem.slug"
                    />
                    <UpgradeCardView v-if="previewType === 'upgrade' && previewItem" :upgrade="previewItem" />
                </div>
                <DrawerFooter>
                    <div class="flex justify-center gap-2">
                        <Button v-if="previewType === 'character'" variant="default" @click="add(previewItem)"> Add To List </Button>
                        <Button v-if="previewType === 'upgrade'" variant="default" @click="addUpgrade(previewItem)"> Add To List </Button>
                        <DrawerClose as-child>
                            <Button variant="outline">Close</Button>
                        </DrawerClose>
                    </div>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>
</template>
