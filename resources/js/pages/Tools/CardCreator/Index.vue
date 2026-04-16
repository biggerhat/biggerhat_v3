<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Share2, Trash2 } from 'lucide-vue-next';
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
}

const props = defineProps<{
    characters: CustomCharacter[];
    upgrades: CustomUpgrade[];
}>();

const activeTab = ref<'characters' | 'crew_cards' | 'upgrades'>('characters');

const crewCards = computed(() => props.upgrades.filter((u) => u.domain === 'crew'));
const characterUpgrades = computed(() => props.upgrades.filter((u) => u.domain === 'character'));

const deleteDialogOpen = ref(false);
const deleteTarget = ref<{ id: number; display_name: string; type: 'character' | 'upgrade' } | null>(null);
const deleting = ref(false);

const confirmDeleteCharacter = (character: CustomCharacter) => {
    deleteTarget.value = { id: character.id, display_name: character.display_name, type: 'character' };
    deleteDialogOpen.value = true;
};

const confirmDeleteUpgrade = (upgrade: CustomUpgrade) => {
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
    await fetch(deleteRoute, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
    });
    deleting.value = false;
    deleteDialogOpen.value = false;
    deleteTarget.value = null;
    router.reload({ only: ['characters', 'upgrades'] });
};

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
            <div class="mb-6 flex items-center gap-4 border-b">
                <button
                    class="border-b-2 px-1 pb-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'characters' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'characters'"
                >
                    Characters
                    <Badge v-if="characters.length" variant="secondary" class="ml-1.5 text-[10px]">{{ characters.length }}</Badge>
                </button>
                <button
                    class="border-b-2 px-1 pb-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'crew_cards' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'crew_cards'"
                >
                    Crew Cards
                    <Badge v-if="crewCards.length" variant="secondary" class="ml-1.5 text-[10px]">{{ crewCards.length }}</Badge>
                </button>
                <button
                    class="border-b-2 px-1 pb-2 text-sm font-medium transition-colors"
                    :class="
                        activeTab === 'upgrades' ? 'border-primary text-primary' : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'upgrades'"
                >
                    Upgrades
                    <Badge v-if="characterUpgrades.length" variant="secondary" class="ml-1.5 text-[10px]">{{ characterUpgrades.length }}</Badge>
                </button>
            </div>

            <!-- ═══ Characters tab ═══ -->
            <div v-if="activeTab === 'characters'">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Your Custom Characters</h2>
                    <Link :href="route('tools.card_creator.create')">
                        <Button size="sm"><Plus class="mr-1 size-4" /> New Character</Button>
                    </Link>
                </div>

                <div v-if="characters.length === 0" class="rounded-lg border border-dashed p-12 text-center">
                    <div class="text-muted-foreground">No custom characters yet.</div>
                    <Link :href="route('tools.card_creator.create')" class="mt-4 inline-block">
                        <Button><Plus class="mr-1 size-4" /> Create Your First</Button>
                    </Link>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Card v-for="character in characters" :key="character.id" class="group overflow-hidden transition-shadow hover:shadow-md">
                        <div class="relative aspect-[550/950] max-h-48 overflow-hidden bg-muted">
                            <div class="flex h-full items-center justify-center text-muted-foreground">
                                <FactionLogo v-if="character.faction" :faction="character.faction" class-name="size-12 opacity-30" />
                            </div>
                            <Badge class="absolute right-2 top-2 bg-purple-600 text-[9px] text-white">Custom</Badge>
                        </div>
                        <CardContent class="p-3">
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
                            <div class="mt-2 flex items-center gap-1">
                                <Link :href="route('tools.card_creator.edit', character.id)" class="flex-1">
                                    <Button variant="outline" size="sm" class="w-full text-xs"><Pencil class="mr-1 size-3" /> Edit</Button>
                                </Link>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-xs"
                                    aria-label="Copy share link"
                                    @click="copyCharacterShareLink(character)"
                                >
                                    <Check v-if="copiedId === 'char-' + character.id" class="size-3 text-green-500" />
                                    <Share2 v-else class="size-3" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-xs text-destructive hover:bg-destructive/10"
                                    aria-label="Delete"
                                    @click="confirmDeleteCharacter(character)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- ═══ Crew Cards tab ═══ -->
            <div v-if="activeTab === 'crew_cards'">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Your Custom Crew Cards</h2>
                    <Link :href="route('tools.card_creator.upgrades.create', { domain: 'crew' })">
                        <Button size="sm"><Plus class="mr-1 size-4" /> New Crew Card</Button>
                    </Link>
                </div>

                <div v-if="crewCards.length === 0" class="rounded-lg border border-dashed p-12 text-center">
                    <div class="text-muted-foreground">No custom crew cards yet.</div>
                    <Link :href="route('tools.card_creator.upgrades.create', { domain: 'crew' })" class="mt-4 inline-block">
                        <Button><Plus class="mr-1 size-4" /> Create Your First</Button>
                    </Link>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Card v-for="card in crewCards" :key="card.id" class="group overflow-hidden transition-shadow hover:shadow-md">
                        <div class="relative aspect-[550/950] max-h-48 overflow-hidden bg-muted">
                            <div class="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
                                <FactionLogo v-if="card.faction" :faction="card.faction" class-name="size-12 opacity-30" />
                                <div v-else class="size-12 rounded-full border-2 border-muted-foreground/10" />
                                <span class="text-[10px] font-medium uppercase tracking-wider opacity-40">Crew Card</span>
                            </div>
                            <Badge class="absolute right-2 top-2 bg-purple-600 text-[9px] text-white">Custom</Badge>
                        </div>
                        <CardContent class="p-3">
                            <div class="mb-1 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold">{{ card.display_name }}</div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <FactionLogo v-if="card.faction" :faction="card.faction" class-name="size-3" />
                                        <span v-if="card.master_name">{{ card.master_name }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center gap-1">
                                <Link :href="route('tools.card_creator.upgrades.edit', card.id)" class="flex-1">
                                    <Button variant="outline" size="sm" class="w-full text-xs"><Pencil class="mr-1 size-3" /> Edit</Button>
                                </Link>
                                <Button variant="outline" size="sm" class="text-xs" aria-label="Copy share link" @click="copyUpgradeShareLink(card)">
                                    <Check v-if="copiedId === 'upg-' + card.id" class="size-3 text-green-500" />
                                    <Share2 v-else class="size-3" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-xs text-destructive hover:bg-destructive/10"
                                    aria-label="Delete"
                                    @click="confirmDeleteUpgrade(card)"
                                >
                                    <Trash2 class="size-3" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- ═══ Upgrades tab ═══ -->
            <div v-if="activeTab === 'upgrades'">
                <div class="mb-6 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Your Custom Upgrades</h2>
                    <Link :href="route('tools.card_creator.upgrades.create', { domain: 'character' })">
                        <Button size="sm"><Plus class="mr-1 size-4" /> New Upgrade</Button>
                    </Link>
                </div>

                <div v-if="characterUpgrades.length === 0" class="rounded-lg border border-dashed p-12 text-center">
                    <div class="text-muted-foreground">No custom upgrades yet.</div>
                    <Link :href="route('tools.card_creator.upgrades.create', { domain: 'character' })" class="mt-4 inline-block">
                        <Button><Plus class="mr-1 size-4" /> Create Your First</Button>
                    </Link>
                </div>

                <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <Card v-for="upgrade in characterUpgrades" :key="upgrade.id" class="group overflow-hidden transition-shadow hover:shadow-md">
                        <div class="relative aspect-[550/950] max-h-48 overflow-hidden bg-muted">
                            <div class="flex h-full flex-col items-center justify-center gap-2 text-muted-foreground">
                                <FactionLogo v-if="upgrade.faction" :faction="upgrade.faction" class-name="size-12 opacity-30" />
                                <div v-else class="size-12 rounded-full border-2 border-muted-foreground/10" />
                                <span class="text-[10px] font-medium uppercase tracking-wider opacity-40">Upgrade</span>
                            </div>
                            <Badge class="absolute right-2 top-2 bg-purple-600 text-[9px] text-white">Custom</Badge>
                        </div>
                        <CardContent class="p-3">
                            <div class="mb-1 flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold">{{ upgrade.display_name }}</div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                        <FactionLogo v-if="upgrade.faction" :faction="upgrade.faction" class-name="size-3" />
                                        <span>Upgrade</span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 flex items-center gap-1">
                                <Link :href="route('tools.card_creator.upgrades.edit', upgrade.id)" class="flex-1">
                                    <Button variant="outline" size="sm" class="w-full text-xs"><Pencil class="mr-1 size-3" /> Edit</Button>
                                </Link>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-xs"
                                    aria-label="Copy share link"
                                    @click="copyUpgradeShareLink(upgrade)"
                                >
                                    <Check v-if="copiedId === 'upg-' + upgrade.id" class="size-3 text-green-500" />
                                    <Share2 v-else class="size-3" />
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    class="text-xs text-destructive hover:bg-destructive/10"
                                    aria-label="Delete"
                                    @click="confirmDeleteUpgrade(upgrade)"
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
