<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Head, Link, router } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Share2, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

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
    front_image: string | null;
    back_image: string | null;
    combo_image: string | null;
    updated_at: string;
}

defineProps<{
    characters: CustomCharacter[];
}>();

const deleteDialogOpen = ref(false);
const deleteTarget = ref<CustomCharacter | null>(null);
const deleting = ref(false);

const confirmDelete = (character: CustomCharacter) => {
    deleteTarget.value = character;
    deleteDialogOpen.value = true;
};

const performDelete = async () => {
    if (!deleteTarget.value) return;
    deleting.value = true;
    await fetch(route('tools.card_creator.destroy', deleteTarget.value.id), {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
    });
    deleting.value = false;
    deleteDialogOpen.value = false;
    deleteTarget.value = null;
    router.reload({ only: ['characters'] });
};

const copiedId = ref<number | null>(null);
const copyShareLink = (character: CustomCharacter) => {
    navigator.clipboard.writeText(window.location.origin + route('tools.card_creator.share', character.share_code));
    copiedId.value = character.id;
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
                <div class="px-2 text-sm text-muted-foreground">Create custom characters with full stats, actions, and abilities.</div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 px-4 lg:px-6">
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
                    <!-- Card image preview or placeholder -->
                    <div class="relative max-h-48 overflow-hidden bg-muted" :class="character.combo_image ? 'aspect-[1100/950]' : 'aspect-[550/950]'">
                        <img
                            v-if="character.combo_image"
                            :src="'/storage/' + character.combo_image"
                            :alt="character.display_name"
                            class="h-full w-full object-cover object-top"
                        />
                        <div v-else class="flex h-full items-center justify-center text-muted-foreground">
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
                                <Button variant="outline" size="sm" class="w-full text-xs">
                                    <Pencil class="mr-1 size-3" /> Edit
                                </Button>
                            </Link>
                            <Button variant="outline" size="sm" class="text-xs" @click="copyShareLink(character)">
                                <Check v-if="copiedId === character.id" class="size-3 text-green-500" />
                                <Share2 v-else class="size-3" />
                            </Button>
                            <Button variant="outline" size="sm" class="text-xs text-destructive hover:bg-destructive/10" @click="confirmDelete(character)">
                                <Trash2 class="size-3" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Delete confirmation -->
    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Delete Custom Character</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete <strong>{{ deleteTarget?.display_name }}</strong>? This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <Button variant="outline" @click="deleteDialogOpen = false">Cancel</Button>
                <Button variant="destructive" :disabled="deleting" @click="performDelete">Delete</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
