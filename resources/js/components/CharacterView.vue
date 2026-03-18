<script setup lang="ts">
import AddToWishlist from '@/components/AddToWishlist.vue';
import CharacterCardView from '@/components/CharacterCardView.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Drawer, DrawerClose, DrawerContent, DrawerFooter, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { imageLabel, imageSrc } from '@/composables/useBlueprintImages';
import { useFactionColor } from '@/composables/useFactionColor';
import { isMobileDevice } from '@/composables/useMobileDevice';
import { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowUpCircle,
    BookOpen,
    Check,
    ChevronRight,
    Copy,
    Download,
    ExternalLink,
    FileImage,
    Library,
    Package,
    Radio,
    Star,
    Swords,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

const page = usePage<SharedData>();

const props = defineProps({
    character: {
        type: Object,
        required: true,
    },
    miniature: {
        type: Object,
        required: true,
    },
    relatedCharacters: {
        type: Array,
        required: false,
        default: () => [],
    },
});

const factionInfo = computed(() => page.props.faction_info);

const factionName = computed(() => factionInfo.value[props.character.faction]?.name ?? props.character.faction);

const secondFactionName = computed(() =>
    props.character.second_faction ? (factionInfo.value[props.character.second_faction]?.name ?? props.character.second_faction) : null,
);

const stationLabel = computed(() => {
    if (!props.character.station) return null;
    return props.character.station.charAt(0).toUpperCase() + props.character.station.slice(1);
});

const baseLabel = computed(() => (props.character.base ? `${props.character.base}mm` : null));

const factionBadgeStyle = (faction: string) => {
    const color = useFactionColor(faction);
    return {
        backgroundColor: `hsl(var(--${color}))`,
        color: 'hsl(var(--primary-foreground))',
        borderColor: 'transparent',
    };
};

const downloadPdf = () => {
    const cards = [{ card_type: 'miniature', id: props.miniature.id }];
    const options = { separate_images: false };
    window.open(route('tools.pdf.download', { cards: btoa(JSON.stringify(cards)), options: btoa(JSON.stringify(options)) }), '_blank');
};

const copied = ref(false);
const copyLink = async () => {
    await navigator.clipboard.writeText(window.location.href);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const standardVersions = ['first_edition', 'second_edition', 'third_edition', 'fourth_edition'];
const versionLabels: Record<string, string> = {
    alternate_model: 'Alt',
    special_edition: 'Special',
    nightmare: 'Nightmare',
    rotten_harvest: 'Rotten Harvest',
};
const sortedMiniatures = computed(() => {
    if (!props.character.miniatures?.length) return [];
    return [...props.character.miniatures].sort((a: any, b: any) => {
        const aStd = standardVersions.includes(a.version) ? 0 : 1;
        const bStd = standardVersions.includes(b.version) ? 0 : 1;
        return aStd - bStd;
    });
});

const sculptLabel = (sculpt: any) => {
    const version = !standardVersions.includes(sculpt.version) ? ` (${versionLabels[sculpt.version] ?? sculpt.version})` : '';
    return `${sculpt.display_name}${version}`;
};

const navigateToSculpt = (sculptId: string) => {
    const sculpt = sortedMiniatures.value.find((m: any) => String(m.id) === sculptId);
    if (sculpt) {
        router.visit(route('characters.view', { character: props.character.slug, miniature: sculpt.id, slug: sculpt.slug }));
    }
};

const hasSecondaryContent = computed(
    () =>
        props.character.packages?.length ||
        props.character.lores?.length ||
        props.character.blueprints?.length ||
        props.character.transmissions?.length,
);

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

// ─── Collection ───
const isAuthenticated = computed(() => !!page.props.auth.user);
const collectionIds = computed(() => page.props.auth.collection_miniature_ids ?? []);

const currentMiniatureInCollection = computed(() => collectionIds.value.includes(props.miniature.id));
const standardMiniatures = computed(
    () => props.character.miniatures?.filter((m: any) => m.version === 'fourth_edition' || m.version === 'third_edition') ?? [],
);
const allStandardInCollection = computed(() => {
    if (standardMiniatures.value.length === 0) return false;
    return standardMiniatures.value.every((m: any) => collectionIds.value.includes(m.id));
});

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const collectionProcessing = ref(false);
const toggleMiniature = async () => {
    collectionProcessing.value = true;
    const ids = page.props.auth.collection_miniature_ids;
    const miniatureId = props.miniature.id;

    if (currentMiniatureInCollection.value) {
        const idx = ids.indexOf(miniatureId);
        if (idx !== -1) ids.splice(idx, 1);
    } else {
        if (!ids.includes(miniatureId)) ids.push(miniatureId);
    }

    try {
        await fetch(route('collection.toggle'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ miniature_id: miniatureId }),
        });
    } finally {
        collectionProcessing.value = false;
    }
};

const addAllStandard = async () => {
    collectionProcessing.value = true;
    const ids = page.props.auth.collection_miniature_ids;
    for (const m of standardMiniatures.value) {
        if (!ids.includes(m.id)) ids.push(m.id);
    }

    try {
        await fetch(route('collection.add_character'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ character_id: props.character.id }),
        });
    } finally {
        collectionProcessing.value = false;
    }
};

// ─── Upgrade Drawer ───
const upgradeDrawerOpen = ref(false);
const activeUpgrade = ref<any>(null);

const openUpgradeDrawer = (upgrade: any) => {
    activeUpgrade.value = upgrade;
    upgradeDrawerOpen.value = true;
};

// ─── Token/Marker Drawer ───
const textDrawerOpen = ref(false);
const textDrawerTitle = ref('');
const textDrawerLabel = ref('');
const textDrawerDescription = ref('');
const textDrawerSubtitle = ref('');

const openTextDrawer = (name: string, label: string, description: string | null, subtitle?: string) => {
    textDrawerTitle.value = name;
    textDrawerLabel.value = label;
    textDrawerDescription.value = description || 'No description available.';
    textDrawerSubtitle.value = subtitle || '';
    textDrawerOpen.value = true;
};
</script>

<template>
    <div class="container mx-auto sm:px-4 pb-8 pt-4 lg:pb-16 lg:pt-6">
        <!-- Back link -->
        <Link
            :href="route('factions.view', character.faction)"
            class="group mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground lg:mb-6"
        >
            <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
            Back to {{ factionName }}
        </Link>

        <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
            <!-- Info panel -->
            <div class="order-2 space-y-3 lg:space-y-4">
                <!-- Main info card -->
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center gap-2.5">
                            <FactionLogo :faction="character.faction" class-name="size-8 shrink-0" />
                            <CardTitle class="text-xl leading-tight lg:text-2xl">{{ character.display_name }}</CardTitle>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-if="stationLabel" variant="secondary">{{ stationLabel }}</Badge>
                            <Link :href="route('factions.view', character.faction)">
                                <Badge class="cursor-pointer transition-opacity hover:opacity-80" :style="factionBadgeStyle(character.faction)">
                                    {{ factionName }}
                                </Badge>
                            </Link>
                            <Link v-if="character.second_faction" :href="route('factions.view', character.second_faction)">
                                <Badge
                                    class="cursor-pointer transition-opacity hover:opacity-80"
                                    :style="factionBadgeStyle(character.second_faction)"
                                >
                                    {{ secondFactionName }}
                                </Badge>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Stat block -->
                        <div class="grid grid-cols-3 gap-1.5 sm:gap-2">
                            <div class="rounded-lg border bg-muted/40 px-2 py-1.5 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                                    <GameIcon type="soulstone" class-name="inline text-xs" /> Cost
                                </div>
                                <div class="text-base font-bold">{{ character.cost }}</div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 px-2 py-1.5 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Health</div>
                                <div class="text-base font-bold">{{ character.health }}</div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 px-2 py-1.5 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Speed</div>
                                <div class="text-base font-bold">{{ character.speed }}</div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 px-2 py-1.5 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Defense</div>
                                <div class="text-base font-bold">
                                    {{ character.defense
                                    }}<GameIcon v-if="character.defense_suit" :type="character.defense_suit" class-name="inline text-xs" />
                                </div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 px-2 py-1.5 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Willpower</div>
                                <div class="text-base font-bold">
                                    {{ character.willpower
                                    }}<GameIcon v-if="character.willpower_suit" :type="character.willpower_suit" class-name="inline text-xs" />
                                </div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 px-2 py-1.5 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">Base</div>
                                <div class="text-base font-bold">{{ baseLabel }}</div>
                            </div>
                        </div>

                        <!-- Stat flags -->
                        <div
                            v-if="character.is_unhirable || character.is_beta || character.count > 1 || character.summon_target_number"
                            class="flex flex-wrap gap-1.5"
                        >
                            <Badge v-if="character.is_unhirable" variant="outline">Unhirable</Badge>
                            <Badge v-if="character.is_beta" variant="outline">Beta</Badge>
                            <Badge v-if="character.count > 1" variant="outline">Count ({{ character.count }})</Badge>
                            <Badge v-if="character.summon_target_number" variant="outline">Summon TN {{ character.summon_target_number }}</Badge>
                        </div>

                        <!-- Characteristics -->
                        <div v-if="character.characteristics?.length">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Characteristics</div>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="char in character.characteristics" :key="char.id" variant="secondary">{{ char.name }}</Badge>
                            </div>
                        </div>

                        <!-- Keywords -->
                        <div v-if="character.keywords?.length">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Keywords</div>
                            <div class="flex flex-wrap gap-1.5">
                                <Link v-for="keyword in character.keywords" :key="keyword.id" :href="route('keywords.view', keyword.slug)">
                                    <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">{{ keyword.name }}</Badge>
                                </Link>
                            </div>
                        </div>

                        <!-- Tokens -->
                        <div v-if="character.tokens?.length">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Tokens</div>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="token in character.tokens"
                                    :key="token.id"
                                    variant="outline"
                                    class="cursor-pointer transition-colors hover:bg-accent"
                                    @click="openTextDrawer(token.name, 'Token', token.description)"
                                >
                                    {{ token.name }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Markers -->
                        <div v-if="character.markers?.length">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Markers</div>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="marker in character.markers"
                                    :key="marker.id"
                                    variant="outline"
                                    class="cursor-pointer transition-colors hover:bg-accent"
                                    @click="openTextDrawer(marker.name, 'Marker', marker.description, marker.base ? marker.base + 'mm base' : undefined)"
                                >
                                    {{ marker.name }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Crew Upgrades -->
                        <div v-if="character.crew_upgrades?.length">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Crew Upgrades</div>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="upgrade in character.crew_upgrades"
                                    :key="upgrade.id"
                                    variant="outline"
                                    class="cursor-pointer gap-1 transition-colors hover:bg-accent"
                                    @click="openUpgradeDrawer(upgrade)"
                                >
                                    <Star class="size-3 text-amber-500" />
                                    {{ upgrade.name }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Totem -->
                        <div v-if="character.totem">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Totem</div>
                            <Link
                                :href="
                                    route('characters.view', {
                                        character: character.totem.slug,
                                        miniature: character.totem.standard_miniatures[0].id,
                                        slug: character.totem.standard_miniatures[0].slug,
                                    })
                                "
                            >
                                <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                    {{ character.totem.display_name }}
                                </Badge>
                            </Link>
                        </div>

                        <!-- Totem For -->
                        <div v-if="character.is_totem_for">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Totem For</div>
                            <Link
                                :href="
                                    route('characters.view', {
                                        character: character.is_totem_for.slug,
                                        miniature: character.is_totem_for.standard_miniatures[0].id,
                                        slug: character.is_totem_for.standard_miniatures[0].slug,
                                    })
                                "
                            >
                                <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                    {{ character.is_totem_for.display_name }}
                                </Badge>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                <!-- Sculpt selector -->
                <Card v-if="sortedMiniatures.length > 1">
                    <CardContent class="space-y-2 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Sculpt</div>
                        <Select :model-value="String(miniature.id)" @update:model-value="navigateToSculpt">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="sculpt in sortedMiniatures" :key="sculpt.id" :value="String(sculpt.id)">
                                    {{ sculptLabel(sculpt) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </CardContent>
                </Card>

                <!-- Character Upgrades -->
                <Card v-if="character.character_upgrades?.length">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Character Upgrades</CardTitle>
                    </CardHeader>
                    <CardContent class="px-0 pb-2">
                        <button
                            v-for="upgrade in character.character_upgrades"
                            :key="upgrade.id"
                            class="flex w-full items-center gap-2.5 border-t px-4 py-2.5 text-left text-sm transition-colors hover:bg-accent"
                            @click="openUpgradeDrawer(upgrade)"
                        >
                            <ArrowUpCircle class="size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1 font-medium">{{ upgrade.name }}</span>
                            <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                        </button>
                    </CardContent>
                </Card>

                <!-- Collection & Wishlist -->
                <Card v-if="isAuthenticated">
                    <CardContent class="space-y-2 p-4">
                        <Button
                            class="w-full gap-2"
                            :variant="currentMiniatureInCollection ? 'default' : 'outline'"
                            :class="currentMiniatureInCollection ? 'bg-green-600 hover:bg-green-700' : ''"
                            :disabled="collectionProcessing"
                            @click="toggleMiniature"
                        >
                            <Check v-if="currentMiniatureInCollection" class="size-4" />
                            <Library v-else class="size-4" />
                            {{ currentMiniatureInCollection ? 'In Collection' : 'Add to Collection' }}
                        </Button>
                        <Button
                            v-if="!allStandardInCollection && standardMiniatures.length > 1"
                            variant="outline"
                            class="w-full gap-2"
                            :disabled="collectionProcessing"
                            @click="addAllStandard"
                        >
                            <Library class="size-4" />
                            Add All Standard Sculpts
                        </Button>
                    </CardContent>
                </Card>

                <!-- Wishlist -->
                <AddToWishlist type="character" :id="character.id" :miniatures="character.miniatures ?? []" :current-miniature-id="miniature.id" />

                <!-- Quick Tools — mobile: card pane -->
                <Card class="lg:hidden">
                    <CardContent class="grid grid-cols-2 gap-2 p-4">
                        <Button variant="default" size="sm" class="w-full gap-1.5" @click="downloadPdf">
                            <Download class="size-4" />
                            Download PDF
                        </Button>
                        <Button variant="outline" size="sm" class="w-full gap-1.5" @click="copyLink">
                            <Check v-if="copied" class="size-4 text-green-500" />
                            <Copy v-else class="size-4" />
                            {{ copied ? 'Copied!' : 'Share Link' }}
                        </Button>
                        <Link v-for="keyword in character.keywords" :key="keyword.id" :href="route('keywords.view', keyword.slug)">
                            <Button variant="outline" size="sm" class="w-full gap-1.5">
                                <Swords class="size-4" />
                                {{ keyword.name }}
                            </Button>
                        </Link>
                        <a :href="`/api/v1/characters/${character.slug}`" target="_blank">
                            <Button variant="outline" size="sm" class="w-full gap-1.5">
                                <ExternalLink class="size-4" />
                                API
                            </Button>
                        </a>
                    </CardContent>
                </Card>
            </div>

            <!-- Image section -->
            <div class="order-1 lg:col-span-2">
                <!-- Mobile: flip card -->
                <div v-if="isMobileDevice()" class="mx-auto max-w-xs">
                    <CharacterCardView :miniature="miniature" :show-link="false" />
                </div>
                <!-- Desktop: combination image or front/back side by side -->
                <div v-else-if="miniature.combination_image" class="overflow-hidden rounded-xl shadow-lg">
                    <img
                        :src="`/storage/${miniature.combination_image}`"
                        :alt="miniature.display_name"
                        loading="lazy"
                        decoding="async"
                        class="w-full"
                    />
                </div>
                <div v-else-if="miniature.front_image && miniature.back_image" class="grid grid-cols-2 gap-2 sm:gap-4">
                    <div class="overflow-hidden rounded-xl shadow-lg">
                        <img
                            :src="`/storage/${miniature.front_image}`"
                            :alt="`${miniature.display_name} Front`"
                            loading="lazy"
                            decoding="async"
                            class="w-full"
                        />
                    </div>
                    <div class="overflow-hidden rounded-xl shadow-lg">
                        <img
                            :src="`/storage/${miniature.back_image}`"
                            :alt="`${miniature.display_name} Back`"
                            loading="lazy"
                            decoding="async"
                            class="w-full"
                        />
                    </div>
                </div>
                <div v-else class="mx-auto max-w-sm">
                    <CharacterCardView :miniature="miniature" :show-link="false" />
                </div>

                <!-- Quick Tools — desktop: below image -->
                <div class="mt-4 hidden grid-cols-4 gap-2 lg:grid">
                    <Button variant="default" size="sm" class="w-full gap-1.5" @click="downloadPdf">
                        <Download class="size-4" />
                        Download PDF
                    </Button>
                    <Button variant="outline" size="sm" class="w-full gap-1.5" @click="copyLink">
                        <Check v-if="copied" class="size-4 text-green-500" />
                        <Copy v-else class="size-4" />
                        {{ copied ? 'Copied!' : 'Share Link' }}
                    </Button>
                    <Link v-for="keyword in character.keywords" :key="keyword.id" :href="route('keywords.view', keyword.slug)">
                        <Button variant="outline" size="sm" class="w-full gap-1.5">
                            <Swords class="size-4" />
                            {{ keyword.name }}
                        </Button>
                    </Link>
                    <a :href="`/api/v1/characters/${character.slug}`" target="_blank">
                        <Button variant="outline" size="sm" class="w-full gap-1.5">
                            <ExternalLink class="size-4" />
                            API
                        </Button>
                    </a>
                </div>
            </div>
        </div>

        <!-- Packages, Lore, Blueprints — full-width below -->
        <div v-if="hasSecondaryContent" class="mt-6 lg:mt-8">
            <Separator class="mb-6" />
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Packages -->
                <Card v-if="character.packages?.length">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Packages</CardTitle>
                    </CardHeader>
                    <CardContent class="px-0 pb-2">
                        <Link
                            v-for="pkg in character.packages"
                            :key="pkg.id"
                            :href="route('packages.view', { package: pkg.slug })"
                            class="flex items-center gap-2.5 border-t px-4 py-2.5 text-sm transition-colors hover:bg-accent"
                        >
                            <Package class="size-4 shrink-0 text-muted-foreground" />
                            <span class="min-w-0 flex-1 font-medium">{{ pkg.name }}</span>
                            <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                        </Link>
                    </CardContent>
                </Card>

                <!-- Lore -->
                <Card v-if="character.lores?.length">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Lore</CardTitle>
                    </CardHeader>
                    <CardContent class="px-0 pb-2">
                        <Link
                            v-for="lore in character.lores"
                            :key="lore.id"
                            :href="route('lores.index', { name_search: lore.name })"
                            class="block border-t px-4 py-2.5 transition-colors hover:bg-accent"
                        >
                            <div class="flex items-center gap-2">
                                <BookOpen class="size-4 shrink-0 text-muted-foreground" />
                                <span class="min-w-0 flex-1 text-sm font-medium">{{ lore.name }}</span>
                                <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                            </div>
                            <div v-if="lore.media?.length" class="ml-6 mt-1 flex flex-wrap gap-1">
                                <Badge v-for="media in lore.media" :key="media.id" variant="outline" class="text-[10px]">
                                    {{ media.name }}
                                </Badge>
                            </div>
                        </Link>
                    </CardContent>
                </Card>

                <!-- Build Instructions -->
                <Card v-if="character.blueprints?.length">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Build Instructions</CardTitle>
                    </CardHeader>
                    <CardContent class="px-0 pb-2">
                        <Dialog v-for="bp in character.blueprints" :key="bp.id">
                            <DialogTrigger as-child>
                                <button
                                    class="flex w-full cursor-pointer items-center gap-2.5 border-t px-4 py-2.5 text-left transition-colors hover:bg-accent"
                                >
                                    <img
                                        v-if="bp.image_path"
                                        :src="imageSrc(bp.image_path)"
                                        :alt="bp.name"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-8 w-10 shrink-0 rounded object-contain"
                                    />
                                    <FileImage v-else class="size-4 shrink-0 text-muted-foreground" />
                                    <span class="min-w-0 flex-1 text-sm font-medium">{{ bp.name }}</span>
                                    <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                                </button>
                            </DialogTrigger>
                            <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
                                <DialogTitle class="text-lg font-semibold">{{ bp.name }}</DialogTitle>
                                <DialogDescription class="text-sm text-muted-foreground">
                                    {{ bp.image_path ? imageLabel(bp.image_path) : 'Assembly diagram' }}
                                </DialogDescription>
                                <img v-if="bp.image_path" :src="imageSrc(bp.image_path)" :alt="bp.name" class="mt-2 w-full rounded-lg border" />
                            </DialogContent>
                        </Dialog>
                    </CardContent>
                </Card>

                <!-- Transmissions -->
                <Card v-if="character.transmissions?.length">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Across the Aethervox</CardTitle>
                    </CardHeader>
                    <CardContent class="px-0 pb-2">
                        <a
                            v-for="tx in character.transmissions"
                            :key="tx.id"
                            :href="tx.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-2.5 border-t px-4 py-2.5 text-sm transition-colors hover:bg-accent"
                        >
                            <Radio class="size-4 shrink-0 text-muted-foreground" />
                            <div class="min-w-0 flex-1">
                                <div class="font-medium">{{ tx.title }}</div>
                                <div class="flex items-center gap-1 text-xs text-muted-foreground">
                                    <span v-if="tx.channel">{{ tx.channel.name }}</span>
                                    <span v-if="tx.channel && tx.release_date">&middot;</span>
                                    <span v-if="tx.release_date">{{ formatDate(tx.release_date) }}</span>
                                </div>
                            </div>
                            <ExternalLink class="size-3.5 shrink-0 text-muted-foreground" />
                        </a>
                        <Link
                            :href="route('channels.index', { character: character.slug })"
                            class="flex items-center justify-center border-t px-4 py-2.5 text-xs font-medium text-primary hover:bg-accent"
                        >
                            View all transmissions
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Summons -->
        <div v-if="character.summons?.length || character.summoned_by?.length" class="mt-8 lg:mt-12">
            <Separator label="Summons" class="mb-6" />
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4">
                <Link
                    v-for="linked in character.summons"
                    :key="'summons-' + linked.id"
                    :href="
                        route('characters.view', {
                            character: linked.slug,
                            miniature: linked.miniatures?.[0]?.id,
                            slug: linked.miniatures?.[0]?.slug,
                        })
                    "
                >
                    <Card class="h-full transition-colors hover:bg-accent/50">
                        <CardContent class="flex items-center gap-2 p-3">
                            <FactionLogo v-if="linked.faction" :faction="linked.faction" class-name="size-5 shrink-0" />
                            <div class="min-w-0">
                                <span class="text-xs font-medium leading-tight sm:text-sm">{{ linked.display_name }}</span>
                                <div class="text-[10px] text-muted-foreground">Summons</div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
                <Link
                    v-for="linked in character.summoned_by"
                    :key="'summoned-by-' + linked.id"
                    :href="
                        route('characters.view', {
                            character: linked.slug,
                            miniature: linked.miniatures?.[0]?.id,
                            slug: linked.miniatures?.[0]?.slug,
                        })
                    "
                >
                    <Card class="h-full transition-colors hover:bg-accent/50">
                        <CardContent class="flex items-center gap-2 p-3">
                            <FactionLogo v-if="linked.faction" :faction="linked.faction" class-name="size-5 shrink-0" />
                            <div class="min-w-0">
                                <span class="text-xs font-medium leading-tight sm:text-sm">{{ linked.display_name }}</span>
                                <div class="text-[10px] text-muted-foreground">Summoned by</div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>

        <!-- Replaces -->
        <div v-if="character.replaces_into?.length || character.replaced_by?.length" class="mt-8 lg:mt-12">
            <Separator label="Replaces" class="mb-6" />
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4">
                <Link
                    v-for="linked in character.replaces_into"
                    :key="'replaces-' + linked.id"
                    :href="
                        route('characters.view', {
                            character: linked.slug,
                            miniature: linked.miniatures?.[0]?.id,
                            slug: linked.miniatures?.[0]?.slug,
                        })
                    "
                >
                    <Card class="h-full transition-colors hover:bg-accent/50">
                        <CardContent class="flex items-center gap-2 p-3">
                            <FactionLogo v-if="linked.faction" :faction="linked.faction" class-name="size-5 shrink-0" />
                            <div class="min-w-0">
                                <span class="text-xs font-medium leading-tight sm:text-sm">{{ linked.display_name }}</span>
                                <div class="text-[10px] text-muted-foreground">Replaces into</div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
                <Link
                    v-for="linked in character.replaced_by"
                    :key="'replaced-by-' + linked.id"
                    :href="
                        route('characters.view', {
                            character: linked.slug,
                            miniature: linked.miniatures?.[0]?.id,
                            slug: linked.miniatures?.[0]?.slug,
                        })
                    "
                >
                    <Card class="h-full transition-colors hover:bg-accent/50">
                        <CardContent class="flex items-center gap-2 p-3">
                            <FactionLogo v-if="linked.faction" :faction="linked.faction" class-name="size-5 shrink-0" />
                            <div class="min-w-0">
                                <span class="text-xs font-medium leading-tight sm:text-sm">{{ linked.display_name }}</span>
                                <div class="text-[10px] text-muted-foreground">Replaced by</div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>

        <!-- Related Characters -->
        <div v-if="relatedCharacters.length > 0" class="mt-8 lg:mt-12">
            <Separator label="Related Characters" class="mb-6" />
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4">
                <Link
                    v-for="related in relatedCharacters as any[]"
                    :key="related.slug"
                    :href="route('characters.view', { character: related.slug, miniature: related.miniature_id, slug: related.miniature_slug })"
                >
                    <Card class="h-full transition-colors hover:bg-accent/50">
                        <CardContent class="flex items-center gap-2 p-3">
                            <FactionLogo v-if="related.faction" :faction="related.faction" class-name="size-5 shrink-0" />
                            <span class="text-xs font-medium leading-tight sm:text-sm">{{ related.display_name }}</span>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </div>

    <!-- Upgrade Drawer -->
    <Drawer v-model:open="upgradeDrawerOpen">
        <DrawerContent>
            <div v-if="activeUpgrade" class="mx-auto w-full max-w-sm">
                <DrawerHeader class="pb-2">
                    <DrawerTitle class="text-center">{{ activeUpgrade.name }}</DrawerTitle>
                    <div class="mt-1 text-center text-xs text-muted-foreground">
                        {{ activeUpgrade.domain === 'crew' ? 'Crew Upgrade' : 'Character Upgrade' }}
                    </div>
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
                            :show-link="true"
                        />
                    </div>
                    <div v-else class="py-8 text-center text-sm text-muted-foreground">
                        No card image available
                        <div class="mt-2">
                            <Link :href="route('upgrades.view', activeUpgrade.slug)">
                                <Button variant="outline" size="sm">View Upgrade Page</Button>
                            </Link>
                        </div>
                    </div>
                </div>
                <DrawerFooter class="shrink-0 pt-2">
                    <DrawerClose as-child>
                        <Button variant="outline">Close</Button>
                    </DrawerClose>
                </DrawerFooter>
            </div>
        </DrawerContent>
    </Drawer>

    <!-- Token/Marker Drawer -->
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
