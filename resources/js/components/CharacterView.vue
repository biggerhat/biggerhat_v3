<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useFactionColor } from '@/composables/useFactionColor';
import { isMobileDevice } from '@/composables/useMobileDevice';
import { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, Copy, Download, ExternalLink, Library, Swords } from 'lucide-vue-next';
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

const factionInfo = computed(() => (page.props as any).faction_info as Record<string, { name: string; slug: string; color: string; logo: string }>);

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

const primaryKeyword = computed(() => props.character.keywords?.[0] ?? null);

// ─── Collection ───
const isAuthenticated = computed(() => !!page.props.auth.user);
const collectionIds = computed(() => page.props.auth.collection_miniature_ids ?? []);

const currentMiniatureInCollection = computed(() => collectionIds.value.includes(props.miniature.id));
const allStandardInCollection = computed(() => {
    const standardMinis = props.character.miniatures?.filter((m: any) => m.version === 'fourth_edition' || m.version === 'third_edition') ?? [];
    if (standardMinis.length === 0) return false;
    return standardMinis.every((m: any) => collectionIds.value.includes(m.id));
});

const collectionProcessing = ref(false);
const toggleMiniature = () => {
    collectionProcessing.value = true;
    router.post(route('collection.toggle'), { miniature_id: props.miniature.id }, {
        preserveScroll: true,
        onFinish: () => (collectionProcessing.value = false),
    });
};

const addAllStandard = () => {
    collectionProcessing.value = true;
    router.post(route('collection.add_character'), { character_id: props.character.id }, {
        preserveScroll: true,
        onFinish: () => (collectionProcessing.value = false),
    });
};
</script>

<template>
    <div class="container mx-auto px-4 pb-8 pt-4 lg:pb-16 lg:pt-6">
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
            <div class="order-2 space-y-3 lg:order-2 lg:space-y-4">
                <!-- Main info card -->
                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex items-center gap-2.5">
                            <FactionLogo :faction="character.faction" class-name="h-8 w-8 shrink-0" />
                            <CardTitle class="text-xl leading-tight lg:text-2xl">{{ character.display_name }}</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Station & faction badges -->
                        <div class="flex flex-wrap gap-2">
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

                        <!-- Stat block -->
                        <div class="grid grid-cols-3 gap-2 sm:gap-3">
                            <div class="rounded-lg border bg-muted/40 p-2 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground sm:text-xs">
                                    <GameIcon type="soulstone" class-name="inline text-xs sm:text-sm" /> Cost
                                </div>
                                <div class="text-base font-bold sm:text-lg">{{ character.cost }}</div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 p-2 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground sm:text-xs">Health</div>
                                <div class="text-base font-bold sm:text-lg">{{ character.health }}</div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 p-2 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground sm:text-xs">Speed</div>
                                <div class="text-base font-bold sm:text-lg">{{ character.speed }}</div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 p-2 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground sm:text-xs">Defense</div>
                                <div class="text-base font-bold sm:text-lg">
                                    {{ character.defense
                                    }}<GameIcon v-if="character.defense_suit" :type="character.defense_suit" class-name="inline text-xs sm:text-sm" />
                                </div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 p-2 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground sm:text-xs">Willpower</div>
                                <div class="text-base font-bold sm:text-lg">
                                    {{ character.willpower
                                    }}<GameIcon
                                        v-if="character.willpower_suit"
                                        :type="character.willpower_suit"
                                        class-name="inline text-xs sm:text-sm"
                                    />
                                </div>
                            </div>
                            <div class="rounded-lg border bg-muted/40 p-2 text-center">
                                <div class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground sm:text-xs">Base</div>
                                <div class="text-base font-bold sm:text-lg">{{ baseLabel }}</div>
                            </div>
                        </div>

                        <!-- Stat flags -->
                        <div
                            v-if="
                                character.generates_stone ||
                                character.is_unhirable ||
                                character.is_beta ||
                                character.count > 1 ||
                                character.summon_target_number
                            "
                            class="flex flex-wrap gap-1.5"
                        >
                            <Badge v-if="character.generates_stone" variant="outline">
                                <GameIcon type="soulstone" class-name="mr-1 inline text-xs" />Generates Soulstone
                            </Badge>
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

                        <!-- Crew Upgrades -->
                        <div v-if="character.crew_upgrades?.length">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Crew Upgrades</div>
                            <div class="flex flex-wrap gap-1.5">
                                <Link v-for="upgrade in character.crew_upgrades" :key="upgrade.id" :href="route('upgrades.view', upgrade.slug)">
                                    <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">{{ upgrade.name }}</Badge>
                                </Link>
                            </div>
                        </div>

                        <!-- Character Upgrades -->
                        <div v-if="character.character_upgrades?.length">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Character Upgrades</div>
                            <div class="flex flex-wrap gap-1.5">
                                <Link v-for="upgrade in character.character_upgrades" :key="upgrade.id" :href="route('upgrades.view', upgrade.slug)">
                                    <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">{{ upgrade.name }}</Badge>
                                </Link>
                            </div>
                        </div>

                        <!-- Totem -->
                        <div v-if="character.totem">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Totem</div>
                            <div class="flex flex-wrap gap-1.5">
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
                        </div>

                        <!-- Totem For -->
                        <div v-if="character.is_totem_for">
                            <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Totem For</div>
                            <div class="flex flex-wrap gap-1.5">
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
                        </div>
                    </CardContent>
                </Card>

                <!-- Sculpt selector -->
                <Card v-if="character.miniatures?.length > 1">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Sculpts</CardTitle>
                    </CardHeader>
                    <CardContent class="px-0 pb-2">
                        <Link
                            v-for="sculpt in character.miniatures"
                            :key="sculpt.id"
                            :href="route('characters.view', { character: character.slug, miniature: sculpt.id, slug: sculpt.slug })"
                            class="block border-t px-4 py-2 text-sm transition-colors hover:bg-accent"
                            :class="{ 'bg-accent/60 font-medium': sculpt.id === miniature.id }"
                        >
                            {{ sculpt.display_name }}
                        </Link>
                    </CardContent>
                </Card>

                <!-- Collection -->
                <Card v-if="isAuthenticated">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Collection</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-1 gap-2">
                        <button
                            class="inline-flex items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-50"
                            :class="
                                currentMiniatureInCollection
                                    ? 'bg-green-600 text-white hover:bg-green-700'
                                    : 'border border-input bg-background hover:bg-accent hover:text-accent-foreground'
                            "
                            :disabled="collectionProcessing"
                            @click="toggleMiniature"
                        >
                            <Check v-if="currentMiniatureInCollection" class="h-4 w-4" />
                            <Library v-else class="h-4 w-4" />
                            {{ currentMiniatureInCollection ? 'In Collection' : 'Add This Sculpt' }}
                        </button>
                        <button
                            v-if="!allStandardInCollection"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50"
                            :disabled="collectionProcessing"
                            @click="addAllStandard"
                        >
                            <Library class="h-4 w-4" />
                            Add All Standard Sculpts
                        </button>
                    </CardContent>
                </Card>

                <!-- Quick Tools -->
                <Card>
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-semibold uppercase tracking-wider text-muted-foreground">Quick Tools</CardTitle>
                    </CardHeader>
                    <CardContent class="grid grid-cols-2 gap-2">
                        <button
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-3 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                            @click="downloadPdf"
                        >
                            <Download class="h-4 w-4" />
                            PDF
                        </button>
                        <button
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                            @click="copyLink"
                        >
                            <Check v-if="copied" class="h-4 w-4 text-green-500" />
                            <Copy v-else class="h-4 w-4" />
                            {{ copied ? 'Copied!' : 'Share' }}
                        </button>
                        <Link
                            v-if="primaryKeyword"
                            :href="route('keywords.view', primaryKeyword.slug)"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            <Swords class="h-4 w-4" />
                            Keyword
                        </Link>
                        <a
                            :href="`/api/v1/characters/${character.slug}`"
                            target="_blank"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-input bg-background px-3 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                        >
                            <ExternalLink class="h-4 w-4" />
                            API
                        </a>
                    </CardContent>
                </Card>
            </div>

            <!-- Image section -->
            <div class="order-1 lg:order-1 lg:col-span-2">
                <!-- Mobile: flip card -->
                <div v-if="isMobileDevice()" class="mx-auto max-w-xs">
                    <CharacterCardView :miniature="miniature" :show-link="false" />
                </div>
                <!-- Desktop: combination image or front/back side by side -->
                <div v-else-if="miniature.combination_image" class="overflow-hidden rounded-xl shadow-lg">
                    <img :src="`/storage/${miniature.combination_image}`" :alt="miniature.display_name" class="w-full" />
                </div>
                <div v-else-if="miniature.front_image && miniature.back_image" class="grid grid-cols-2 gap-4">
                    <div class="overflow-hidden rounded-xl shadow-lg">
                        <img :src="`/storage/${miniature.front_image}`" :alt="`${miniature.display_name} Front`" class="w-full" />
                    </div>
                    <div class="overflow-hidden rounded-xl shadow-lg">
                        <img :src="`/storage/${miniature.back_image}`" :alt="`${miniature.display_name} Back`" class="w-full" />
                    </div>
                </div>
                <div v-else class="mx-auto max-w-sm">
                    <CharacterCardView :miniature="miniature" :show-link="false" />
                </div>
            </div>
        </div>

        <!-- Related Characters -->
        <div v-if="relatedCharacters.length > 0" class="mt-8 lg:mt-12">
            <Separator label="Related Characters" class="mb-6" />
            <div class="grid grid-cols-2 gap-2 sm:gap-3 lg:grid-cols-4">
                <Link
                    v-for="related in relatedCharacters as any[]"
                    :key="related.slug"
                    :href="route('characters.view', { character: related.slug, miniature: related.miniature_id, slug: related.miniature_slug })"
                >
                    <Card class="h-full transition-colors hover:bg-accent/50">
                        <CardHeader class="p-3 sm:p-4">
                            <div class="flex items-center gap-2">
                                <FactionLogo v-if="related.faction" :faction="related.faction" class-name="h-4 w-4 shrink-0 sm:h-5 sm:w-5" />
                                <CardTitle class="text-xs leading-tight sm:text-sm">{{ related.display_name }}</CardTitle>
                            </div>
                        </CardHeader>
                    </Card>
                </Link>
            </div>
        </div>
    </div>
</template>
