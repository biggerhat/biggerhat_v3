<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useCardImageEvents } from '@/composables/useCardImageEvents';
import { useToast } from '@/composables/useToast';
import { cacheBustedImagePath } from '@/lib/cacheBustedImage';
import { CARD_HOVER } from '@/lib/cardHover';
import { csrfToken } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, CheckSquare, Copy, Download, Loader2, Pencil, Plus, RefreshCw, Search, Share2, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CustomCharacter {
    id: number;
    name: string;
    title: string | null;
    display_name: string;
    slug: string;
    faction: string;
    second_faction: string | null;
    station: string;
    cost: number | null;
    health: number;
    share_code: string;
    updated_at: string;
    // Saved card images (rendered server-side shortly after each save) and the art fallback while one is pending.
    front_image?: string | null;
    thumb_image?: string | null;
    combination_image?: string | null;
    card_image_generated_at?: string | null;
    card_art_path?: string | null;
    card_image_pending?: boolean;
    // A Leader/Totem tied to a STILL-ACTIVE Campaign crew can't be deleted
    // here — see performDelete(). is_campaign_leader/is_campaign_totem stay
    // true forever (only cleared if a replacement Leader supersedes this
    // row), so campaign_still_live is what actually gates the delete button;
    // once the campaign ends or is deleted, deletion is allowed again.
    is_campaign_leader: boolean;
    is_campaign_totem: boolean;
    campaign_still_live: boolean;
}

interface CustomUpgrade {
    id: number;
    name: string;
    display_name: string;
    slug: string;
    domain: string;
    type: string | null;
    faction: string | null;
    master_name: string | null;
    share_code: string;
    updated_at: string;
    front_image?: string | null;
    thumb_image?: string | null;
    combination_image?: string | null;
    card_image_generated_at?: string | null;
    card_image_pending?: boolean;
    // A crew card saved from Starting Arsenal, while its Campaign crew is
    // still active, can't be deleted here — see performDelete(). Once that
    // campaign ends or is deleted, campaign_still_live flips false and
    // deletion is allowed again (is_campaign_crew_card itself never clears).
    is_campaign_crew_card: boolean;
    campaign_still_live: boolean;
}

const props = defineProps<{
    characters: CustomCharacter[];
    upgrades: CustomUpgrade[];
}>();

const toast = useToast();

const activeTab = ref<'characters' | 'crew_cards' | 'upgrades'>('characters');

// ── Crew Cards / Upgrades tabs share one tile ──
const upgradeTab = computed(() =>
    activeTab.value === 'crew_cards'
        ? {
              heading: 'Your Custom Crew Cards',
              domain: 'crew',
              newLabel: 'New Crew Card',
              itemLabel: 'Crew Card',
              items: crewCards.value,
              emptyTitle: 'No custom crew cards yet',
              emptyDescription: 'Design a crew card for your crew. It gets a shareable card image you can download.',
          }
        : {
              heading: 'Your Custom Upgrades',
              domain: 'character',
              newLabel: 'New Upgrade',
              itemLabel: 'Upgrade',
              items: characterUpgrades.value,
              emptyTitle: 'No custom upgrades yet',
              emptyDescription: 'Make an upgrade with your own text. It gets a shareable card image you can download.',
          },
);
const brokenUpgradeImages = ref<number[]>([]);
const hasSavedUpgradeImage = (u: CustomUpgrade) => !!u.front_image && !brokenUpgradeImages.value.includes(u.id);
const rerenderingUpgradeIds = ref<number[]>([]);
const isUpgradeRendering = (u: CustomUpgrade) => !!u.card_image_pending || rerenderingUpgradeIds.value.includes(u.id);
const rerenderUpgrade = async (u: CustomUpgrade) => {
    rerenderingUpgradeIds.value.push(u.id);
    const res = await fetch(route('tools.card_creator.upgrades.rerender', u.id), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
    if (!res.ok) {
        rerenderingUpgradeIds.value = rerenderingUpgradeIds.value.filter((id) => id !== u.id);
        toast.error('Could not start the render — please try again in a moment.');
        return;
    }
    router.reload({
        only: ['upgrades'],
        onFinish: () => (rerenderingUpgradeIds.value = rerenderingUpgradeIds.value.filter((id) => id !== u.id)),
    });
};
const duplicateUpgrade = async (u: CustomUpgrade) => {
    if (duplicatingId.value !== null) return;
    duplicatingId.value = 'upg-' + u.id;
    try {
        const res = await fetch(route('tools.card_creator.upgrades.duplicate', u.id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        const body = await res.json().catch(() => null);
        if (!res.ok || !body?.redirect) {
            toast.error('Could not duplicate', { description: body?.message ?? 'Please try again.' });
            return;
        }
        router.visit(body.redirect);
    } finally {
        duplicatingId.value = null;
    }
};

// A saved image that 404s (deleted file, storage hiccup) drops back to the art / logo instead of a broken-image icon.
const brokenImages = ref<number[]>([]);
const hasSavedImage = (c: CustomCharacter) => !!c.front_image && !brokenImages.value.includes(c.id);

const characterSearch = ref('');
const filteredCharacters = computed(() => {
    const q = characterSearch.value.trim().toLowerCase();
    return q ? props.characters.filter((c) => c.display_name.toLowerCase().includes(q)) : props.characters;
});

// Re-queue the saved card image (a render that failed, or one you want refreshed). The finished event reloads the list.
const rerenderingIds = ref<number[]>([]);
const rerenderCharacter = async (character: CustomCharacter) => {
    rerenderingIds.value.push(character.id);
    const res = await fetch(route('tools.card_creator.rerender', character.id), {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
    });
    if (!res.ok) {
        rerenderingIds.value = rerenderingIds.value.filter((id) => id !== character.id);
        toast.error('Could not start the render — please try again in a moment.');
        return;
    }
    router.reload({
        only: ['characters'],
        onFinish: () => (rerenderingIds.value = rerenderingIds.value.filter((id) => id !== character.id)),
    });
};
// Copies one of your cards into a new one and opens it in the editor.
const duplicatingId = ref<number | string | null>(null);
const duplicateCharacter = async (character: CustomCharacter) => {
    if (duplicatingId.value !== null) return;
    duplicatingId.value = character.id;
    try {
        const res = await fetch(route('tools.card_creator.duplicate', character.id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
        });
        const body = await res.json().catch(() => null);
        if (!res.ok || !body?.redirect) {
            toast.error('Could not duplicate', { description: body?.message ?? 'Please try again.' });
            return;
        }
        router.visit(body.redirect);
    } finally {
        duplicatingId.value = null;
    }
};
const isRendering = (character: CustomCharacter) => !!character.card_image_pending || rerenderingIds.value.includes(character.id);

const crewCards = computed(() => props.upgrades.filter((u) => u.domain === 'crew'));
const characterUpgrades = computed(() => props.upgrades.filter((u) => u.domain === 'character'));

// A Leader/Totem tied to a STILL-ACTIVE Campaign crew can't be deleted here —
// it's still referenced by that crew's advancement log and every game it's
// been hired into. Once the campaign ends or is deleted there's no live crew
// left to break, so deletion is allowed again — server-enforced (see
// CustomCharacterController::destroy()); this just keeps the user from
// hitting the error in the first place, without also blocking the cases the
// backend actually allows.
const isCampaignLinked = (character: CustomCharacter) =>
    (character.is_campaign_leader || character.is_campaign_totem) && character.campaign_still_live;
const isCampaignLinkedCrewCard = (upgrade: CustomUpgrade) => upgrade.is_campaign_crew_card && upgrade.campaign_still_live;

// ─── Bulk select: delete or re-render several cards at once. Runs the same per-card endpoints one after another. ───
const selecting = ref(false);
const selected = ref<string[]>([]); // 'c-<id>' for characters, 'u-<id>' for upgrades / crew cards
const bulkBusy = ref(false);
const isSelected = (key: string) => selected.value.includes(key);
const toggleSelected = (key: string) => {
    selected.value = isSelected(key) ? selected.value.filter((k) => k !== key) : [...selected.value, key];
};
const stopSelecting = () => {
    selecting.value = false;
    selected.value = [];
};
// Cards tied to a live campaign can't be deleted (the server refuses), so they are never offered for selection-delete.
const visibleKeys = computed(() =>
    activeTab.value === 'characters' ? filteredCharacters.value.map((c) => 'c-' + c.id) : upgradeTab.value.items.map((u) => 'u-' + u.id),
);
const selectAllVisible = () => {
    selected.value = [...new Set([...selected.value, ...visibleKeys.value])];
};
const selectedDeletable = computed(() =>
    selected.value.filter((key) => {
        const id = Number(key.slice(2));
        return key.startsWith('c-')
            ? !props.characters.some((c) => c.id === id && isCampaignLinked(c))
            : !props.upgrades.some((u) => u.id === id && isCampaignLinkedCrewCard(u));
    }),
);

const bulkRun = async (keys: string[], request: (kind: 'c' | 'u', id: number) => Promise<Response>, doneLabel: string) => {
    if (bulkBusy.value || keys.length === 0) return;
    bulkBusy.value = true;
    let ok = 0;
    let stoppedBy: string | null = null;
    for (const key of keys) {
        const res = await request(key.startsWith('c-') ? 'c' : 'u', Number(key.slice(2))).catch(() => null);
        if (res?.ok) {
            ok++;
            continue;
        }
        if (res?.status === 429) {
            stoppedBy = 'You are going too fast — wait a minute and try the rest again.';
            break;
        }
        const body = await res?.json().catch(() => null);
        stoppedBy = body?.message ?? 'One of the cards could not be processed.';
    }
    bulkBusy.value = false;
    if (ok > 0) toast.success(`${doneLabel} ${ok} card${ok === 1 ? '' : 's'}.`);
    if (stoppedBy) toast.error(stoppedBy);
    stopSelecting();
    router.reload({ only: ['characters', 'upgrades'] });
};
const bulkDeleteOpen = ref(false);
const bulkDelete = async () => {
    bulkDeleteOpen.value = false;
    await bulkRun(
        selectedDeletable.value,
        (kind, id) =>
            fetch(kind === 'c' ? route('tools.card_creator.destroy', id) : route('tools.card_creator.upgrades.destroy', id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            }),
        'Deleted',
    );
};
const bulkRerender = () =>
    bulkRun(
        selected.value,
        (kind, id) =>
            fetch(kind === 'c' ? route('tools.card_creator.rerender', id) : route('tools.card_creator.upgrades.rerender', id), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
            }),
        'Re-rendering',
    );

const deleteDialogOpen = ref(false);
const deleteTarget = ref<{ id: number; display_name: string; type: 'character' | 'upgrade' } | null>(null);
const deleting = ref(false);

const confirmDeleteCharacter = (character: CustomCharacter) => {
    if (isCampaignLinked(character)) return;
    deleteTarget.value = { id: character.id, display_name: character.display_name, type: 'character' };
    deleteDialogOpen.value = true;
};

const confirmDeleteUpgrade = (upgrade: CustomUpgrade) => {
    if (isCampaignLinkedCrewCard(upgrade)) return;
    deleteTarget.value = { id: upgrade.id, display_name: upgrade.display_name, type: 'upgrade' };
    deleteDialogOpen.value = true;
};

const performDelete = async () => {
    if (!deleteTarget.value) return;
    deleting.value = true;
    const deleteRoute =
        deleteTarget.value.type === 'character'
            ? route('tools.card_creator.destroy', deleteTarget.value.id)
            : route('tools.card_creator.upgrades.destroy', deleteTarget.value.id);
    const response = await fetch(deleteRoute, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken() },
    });
    deleting.value = false;
    deleteDialogOpen.value = false;

    if (!response.ok) {
        const body = await response.json().catch(() => null);
        toast.error(body?.message ?? 'Could not delete — please try again.');
        deleteTarget.value = null;
        return;
    }

    deleteTarget.value = null;
    router.reload({ only: ['characters', 'upgrades'] });
};

// A saved card's image is rendered shortly after each save; swap it in as soon as the server announces it.
useCardImageEvents(usePage<SharedData>().props.auth.user?.id, (event) => {
    if (event.kind === 'custom_character') router.reload({ only: ['characters'] });
    if (event.kind === 'custom_upgrade' || event.kind === 'crew_card') router.reload({ only: ['upgrades'] });
});

const copiedId = ref<string | null>(null);
const copyCharacterShareLink = (character: CustomCharacter) => {
    navigator.clipboard.writeText(window.location.origin + route('tools.card_creator.share', character.share_code));
    copiedId.value = 'char-' + character.id;
    setTimeout(() => (copiedId.value = null), 2000);
};

const copyUpgradeShareLink = (upgrade: CustomUpgrade) => {
    navigator.clipboard.writeText(window.location.origin + route('tools.card_creator.upgrades.share', upgrade.share_code));
    copiedId.value = 'upg-' + upgrade.id;
    setTimeout(() => (copiedId.value = null), 2000);
};

const stationLabel = (station: string | null) => {
    if (!station || station === 'none') return null;
    const map: Record<string, string> = { master: 'Master', enforcer: 'Enforcer', minion: 'Minion', peon: 'Peon' };
    return map[station] ?? station;
};
</script>

<template>
    <Head title="Card Creator" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Card Creator">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">Create custom characters, crew cards, and upgrades.</div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <!-- Tabs -->
            <div class="mb-6 flex items-center gap-3 overflow-x-auto border-b sm:gap-4" role="tablist" aria-label="Card types">
                <button
                    role="tab"
                    :aria-selected="activeTab === 'characters'"
                    class="whitespace-nowrap border-b-2 px-1 pb-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'characters' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'characters'"
                >
                    Characters
                    <Badge v-if="characters.length" variant="secondary" class="ml-1.5 text-2xs">{{ characters.length }}</Badge>
                </button>
                <button
                    role="tab"
                    :aria-selected="activeTab === 'crew_cards'"
                    class="whitespace-nowrap border-b-2 px-1 pb-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'crew_cards' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'crew_cards'"
                >
                    Crew Cards
                    <Badge v-if="crewCards.length" variant="secondary" class="ml-1.5 text-2xs">{{ crewCards.length }}</Badge>
                </button>
                <button
                    role="tab"
                    :aria-selected="activeTab === 'upgrades'"
                    class="whitespace-nowrap border-b-2 px-1 pb-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'upgrades' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'upgrades'"
                >
                    Upgrades
                    <Badge v-if="characterUpgrades.length" variant="secondary" class="ml-1.5 text-2xs">{{ characterUpgrades.length }}</Badge>
                </button>
                <button
                    v-if="characters.length + upgrades.length > 1"
                    type="button"
                    class="mb-2 ml-auto inline-flex shrink-0 items-center gap-1.5 rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-accent hover:text-foreground"
                    :aria-pressed="selecting"
                    @click="selecting ? stopSelecting() : (selecting = true)"
                >
                    <CheckSquare class="size-3.5" /> {{ selecting ? 'Done' : 'Select' }}
                </button>
            </div>

            <div
                v-if="selecting"
                class="sticky bottom-[var(--tabbar-h,0px)] z-20 mb-4 flex flex-wrap items-center gap-2 rounded-lg border bg-background/95 px-3 py-2 text-sm shadow backdrop-blur"
                role="region"
                aria-label="Bulk actions"
            >
                <span class="font-medium" aria-live="polite">{{ selected.length }} selected</span>
                <Button size="sm" variant="ghost" @click="selectAllVisible">Select all shown</Button>
                <Button size="sm" variant="outline" :disabled="bulkBusy || !selected.length" @click="bulkRerender"
                    ><RefreshCw class="mr-1 size-3.5" /> Re-render</Button
                >
                <Button size="sm" variant="destructive" :disabled="bulkBusy || !selectedDeletable.length" @click="bulkDeleteOpen = true"
                    ><Trash2 class="mr-1 size-3.5" /> Delete{{ selectedDeletable.length ? ` ${selectedDeletable.length}` : '' }}</Button
                >
                <Button size="sm" variant="ghost" class="ml-auto" @click="stopSelecting">Cancel</Button>
            </div>

            <!-- ═══ Characters tab ═══ -->
            <div v-if="activeTab === 'characters'">
                <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">Your Custom Characters</h2>
                    <div
                        v-if="characters.length > 6"
                        class="relative order-last w-full sm:order-none sm:ml-auto sm:mr-0 sm:w-auto sm:max-w-[14rem] sm:flex-1"
                    >
                        <Search class="pointer-events-none absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input v-model="characterSearch" placeholder="Search characters…" aria-label="Search your characters" class="h-9 pl-8" />
                    </div>
                    <Link :href="route('tools.card_creator.create')">
                        <Button size="sm"><Plus class="mr-1 size-4" /> New Character</Button>
                    </Link>
                </div>

                <EmptyState
                    v-if="characters.length === 0"
                    title="No custom characters yet"
                    description="Build a card with your own stats, actions and art. Every character gets a shareable card image you can download."
                >
                    <template #action>
                        <Link :href="route('tools.card_creator.create')">
                            <Button><Plus class="mr-1 size-4" /> Create Your First</Button>
                        </Link>
                    </template>
                </EmptyState>

                <p v-else-if="filteredCharacters.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                    No characters match "{{ characterSearch }}".
                </p>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Card v-for="character in filteredCharacters" :key="character.id" :class="['group flex overflow-hidden sm:flex-col', CARD_HOVER]">
                        <div class="relative aspect-[3/4] w-28 shrink-0 overflow-hidden bg-muted sm:w-full">
                            <Link
                                :href="route('tools.card_creator.share', character.share_code)"
                                class="absolute inset-0 z-0"
                                :aria-label="`View ${character.display_name}`"
                            />
                            <button
                                v-if="selecting"
                                type="button"
                                class="absolute inset-0 z-10 flex items-start justify-start p-2 transition-colors"
                                :class="
                                    isSelected('c-' + character.id)
                                        ? 'bg-primary/20 ring-2 ring-inset ring-primary'
                                        : 'bg-background/30 hover:bg-background/10'
                                "
                                :aria-pressed="isSelected('c-' + character.id)"
                                :aria-label="`Select ${character.display_name}`"
                                @click="toggleSelected('c-' + character.id)"
                            >
                                <span
                                    class="flex size-5 items-center justify-center rounded border bg-background"
                                    :class="isSelected('c-' + character.id) ? 'border-primary bg-primary text-primary-foreground' : ''"
                                >
                                    <Check v-if="isSelected('c-' + character.id)" class="size-3.5" />
                                </span>
                            </button>
                            <img
                                v-if="hasSavedImage(character)"
                                :src="
                                    '/storage/' +
                                    cacheBustedImagePath(character.thumb_image || character.front_image!, character.card_image_generated_at)
                                "
                                :alt="character.display_name"
                                class="h-full w-full object-contain"
                                loading="lazy"
                                @error="brokenImages.push(character.id)"
                            />
                            <img
                                v-else-if="character.card_art_path"
                                :src="'/storage/' + character.card_art_path"
                                :alt="character.display_name"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                            <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                                <FactionLogo v-if="character.faction" :faction="character.faction" class-name="size-12 opacity-30" />
                            </div>
                            <span
                                v-if="isRendering(character)"
                                class="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-full bg-background/90 px-2 py-0.5 text-2xs font-medium shadow"
                                role="status"
                            >
                                <Loader2 class="size-3 animate-spin" /> Rendering…
                            </span>
                            <Badge class="pointer-events-none absolute right-2 top-2 hidden bg-purple-600 text-2xs text-white sm:inline-flex"
                                >Custom</Badge
                            >
                            <Badge
                                v-if="isCampaignLinked(character)"
                                class="pointer-events-none absolute left-2 top-2 bg-amber-700 text-2xs text-white"
                                >Campaign</Badge
                            >
                        </div>
                        <CardContent class="min-w-0 flex-1 p-3">
                            <div class="mb-1 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold">{{ character.display_name }}</div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <FactionLogo v-if="character.faction" :faction="character.faction" class-name="size-3" />
                                        <span v-if="stationLabel(character.station)">{{ stationLabel(character.station) }}</span>
                                        <span v-if="character.cost != null">| {{ character.cost }}ss</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                <Link :href="route('tools.card_creator.edit', character.id)" class="min-w-[4.5rem] flex-1">
                                    <Button variant="outline" size="sm" class="w-full text-xs"><Pencil class="mr-1 size-3" /> Edit</Button>
                                </Link>
                                <a
                                    v-if="character.combination_image"
                                    :href="'/storage/' + cacheBustedImagePath(character.combination_image, character.card_image_generated_at)"
                                    :download="`${character.display_name}.png`"
                                    class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border text-xs transition-colors hover:bg-accent [@media(pointer:coarse)]:size-10"
                                    :aria-label="`Download ${character.display_name} card image`"
                                    title="Download card image (front and back)"
                                >
                                    <Download class="size-3" />
                                </a>
                                <Button
                                    v-else-if="!isRendering(character)"
                                    variant="outline"
                                    size="sm"
                                    class="size-8 shrink-0 p-0 text-xs [@media(pointer:coarse)]:size-10"
                                    :aria-label="`Render ${character.display_name} card image`"
                                    title="No card image yet — render it now"
                                    @click="rerenderCharacter(character)"
                                >
                                    <RefreshCw class="size-3" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="size-8 shrink-0 p-0 text-xs [@media(pointer:coarse)]:size-10"
                                    :disabled="duplicatingId === character.id"
                                    :aria-label="`Duplicate ${character.display_name}`"
                                    title="Duplicate"
                                    @click="duplicateCharacter(character)"
                                >
                                    <Loader2 v-if="duplicatingId === character.id" class="size-3 animate-spin" />
                                    <Copy v-else class="size-3" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="size-8 shrink-0 p-0 text-xs [@media(pointer:coarse)]:size-10"
                                    aria-label="Copy share link"
                                    @click="copyCharacterShareLink(character)"
                                >
                                    <Check v-if="copiedId === 'char-' + character.id" class="size-3 text-green-700 dark:text-green-400" />
                                    <Share2 v-else class="size-3" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="isCampaignLinked(character)"
                                    :class="[
                                        'size-8 shrink-0 p-0 text-xs [@media(pointer:coarse)]:size-10',
                                        !isCampaignLinked(character) && 'text-destructive hover:bg-destructive/10',
                                    ]"
                                    :aria-label="isCampaignLinked(character) ? 'Cannot delete — tied to a Campaign crew' : 'Delete'"
                                    :title="isCampaignLinked(character) ? 'Tied to a Campaign crew — delete from the crew instead' : undefined"
                                    @click="confirmDeleteCharacter(character)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- ═══ Crew Cards / Upgrades tabs (same tile, different list) ═══ -->
            <div v-if="activeTab !== 'characters'">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">{{ upgradeTab.heading }}</h2>
                    <Link :href="route('tools.card_creator.upgrades.create', { domain: upgradeTab.domain })">
                        <Button size="sm"><Plus class="mr-1 size-4" /> {{ upgradeTab.newLabel }}</Button>
                    </Link>
                </div>

                <EmptyState v-if="upgradeTab.items.length === 0" :title="upgradeTab.emptyTitle" :description="upgradeTab.emptyDescription">
                    <template #action>
                        <Link :href="route('tools.card_creator.upgrades.create', { domain: upgradeTab.domain })">
                            <Button><Plus class="mr-1 size-4" /> Create Your First</Button>
                        </Link>
                    </template>
                </EmptyState>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Card v-for="card in upgradeTab.items" :key="card.id" :class="['group flex overflow-hidden sm:flex-col', CARD_HOVER]">
                        <div class="relative aspect-[3/4] w-28 shrink-0 overflow-hidden bg-muted sm:w-full">
                            <Link
                                :href="route('tools.card_creator.upgrades.share', card.share_code)"
                                class="absolute inset-0 z-0"
                                :aria-label="`View ${card.display_name}`"
                            />
                            <button
                                v-if="selecting"
                                type="button"
                                class="absolute inset-0 z-10 flex items-start justify-start p-2 transition-colors"
                                :class="
                                    isSelected('u-' + card.id)
                                        ? 'bg-primary/20 ring-2 ring-inset ring-primary'
                                        : 'bg-background/30 hover:bg-background/10'
                                "
                                :aria-pressed="isSelected('u-' + card.id)"
                                :aria-label="`Select ${card.display_name}`"
                                @click="toggleSelected('u-' + card.id)"
                            >
                                <span
                                    class="flex size-5 items-center justify-center rounded border bg-background"
                                    :class="isSelected('u-' + card.id) ? 'border-primary bg-primary text-primary-foreground' : ''"
                                >
                                    <Check v-if="isSelected('u-' + card.id)" class="size-3.5" />
                                </span>
                            </button>
                            <img
                                v-if="hasSavedUpgradeImage(card)"
                                :src="'/storage/' + cacheBustedImagePath(card.thumb_image || card.front_image!, card.card_image_generated_at)"
                                :alt="card.display_name"
                                class="h-full w-full object-contain"
                                loading="lazy"
                                @error="brokenUpgradeImages.push(card.id)"
                            />
                            <div v-else class="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
                                <FactionLogo v-if="card.faction" :faction="card.faction" class-name="size-12 opacity-30" />
                                <div v-else class="size-12 rounded-full border-2 border-muted-foreground/10" />
                                <span class="text-2xs font-medium uppercase tracking-wider opacity-40">{{ upgradeTab.itemLabel }}</span>
                            </div>
                            <span
                                v-if="isUpgradeRendering(card)"
                                class="absolute bottom-2 left-2 inline-flex items-center gap-1 rounded-full bg-background/90 px-2 py-0.5 text-2xs font-medium shadow"
                                role="status"
                            >
                                <Loader2 class="size-3 animate-spin" /> Rendering…
                            </span>
                            <Badge class="pointer-events-none absolute right-2 top-2 hidden bg-purple-600 text-2xs text-white sm:inline-flex"
                                >Custom</Badge
                            >
                            <Badge
                                v-if="isCampaignLinkedCrewCard(card)"
                                class="pointer-events-none absolute left-2 top-2 bg-amber-700 text-2xs text-white"
                            >
                                Campaign
                            </Badge>
                        </div>
                        <CardContent class="min-w-0 flex-1 p-3">
                            <div class="mb-1 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold">{{ card.display_name }}</div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <FactionLogo v-if="card.faction" :faction="card.faction" class-name="size-3" />
                                        <span>{{ card.master_name || upgradeTab.itemLabel }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-1">
                                <Link :href="route('tools.card_creator.upgrades.edit', card.id)" class="min-w-[4.5rem] flex-1">
                                    <Button variant="outline" size="sm" class="w-full text-xs"><Pencil class="mr-1 size-3" /> Edit</Button>
                                </Link>
                                <a
                                    v-if="card.combination_image"
                                    :href="'/storage/' + cacheBustedImagePath(card.combination_image, card.card_image_generated_at)"
                                    :download="`${card.display_name}.png`"
                                    class="inline-flex size-8 shrink-0 items-center justify-center rounded-md border text-xs transition-colors hover:bg-accent [@media(pointer:coarse)]:size-10"
                                    :aria-label="`Download ${card.display_name} card image`"
                                    title="Download card image (front and back)"
                                >
                                    <Download class="size-3" />
                                </a>
                                <Button
                                    v-else-if="!isUpgradeRendering(card)"
                                    variant="outline"
                                    size="sm"
                                    class="size-8 shrink-0 p-0 text-xs [@media(pointer:coarse)]:size-10"
                                    :aria-label="`Render ${card.display_name} card image`"
                                    title="No card image yet — render it now"
                                    @click="rerenderUpgrade(card)"
                                >
                                    <RefreshCw class="size-3" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="size-8 shrink-0 p-0 text-xs [@media(pointer:coarse)]:size-10"
                                    :disabled="duplicatingId === 'upg-' + card.id"
                                    :aria-label="`Duplicate ${card.display_name}`"
                                    title="Duplicate"
                                    @click="duplicateUpgrade(card)"
                                >
                                    <Loader2 v-if="duplicatingId === 'upg-' + card.id" class="size-3 animate-spin" />
                                    <Copy v-else class="size-3" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="size-8 shrink-0 p-0 text-xs [@media(pointer:coarse)]:size-10"
                                    aria-label="Copy share link"
                                    @click="copyUpgradeShareLink(card)"
                                >
                                    <Check v-if="copiedId === 'upg-' + card.id" class="size-3 text-green-700 dark:text-green-400" />
                                    <Share2 v-else class="size-3" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="isCampaignLinkedCrewCard(card)"
                                    :class="[
                                        'size-8 shrink-0 p-0 text-xs [@media(pointer:coarse)]:size-10',
                                        !isCampaignLinkedCrewCard(card) && 'text-destructive hover:bg-destructive/10',
                                    ]"
                                    :aria-label="isCampaignLinkedCrewCard(card) ? 'Cannot delete — tied to a Campaign crew' : 'Delete'"
                                    :title="isCampaignLinkedCrewCard(card) ? 'Tied to a Campaign crew — saved from Starting Arsenal' : undefined"
                                    @click="confirmDeleteUpgrade(card)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete confirmation -->
    <Dialog v-model:open="bulkDeleteOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete {{ selectedDeletable.length }} card{{ selectedDeletable.length === 1 ? '' : 's' }}?</DialogTitle>
                <DialogDescription>
                    This removes them and their saved images and share links.
                    <template v-if="selectedDeletable.length < selected.length"
                        >Cards tied to an active Campaign crew are skipped — they can't be deleted here.</template
                    >
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="bulkDeleteOpen = false">Cancel</Button>
                <Button variant="destructive" @click="bulkDelete">Delete</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Delete Custom {{ deleteTarget?.type === 'upgrade' ? 'Card' : 'Character' }}</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete <strong>{{ deleteTarget?.display_name }}</strong
                    >? This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteDialogOpen = false">Cancel</Button>
                <Button variant="destructive" :disabled="deleting" @click="performDelete">Delete</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
