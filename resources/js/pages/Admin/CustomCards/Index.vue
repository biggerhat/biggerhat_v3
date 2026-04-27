<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router } from '@inertiajs/vue3';
import { Eye, EyeOff, ShieldAlert, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const confirm = useConfirm();

interface CardRow {
    kind: 'character' | 'upgrade';
    id: number;
    share_code: string;
    name: string;
    faction: string | null;
    domain?: string;
    is_public: boolean;
    user: { id: number; name: string } | null;
    created_at: string | null;
    share_url: string;
}

const props = defineProps<{
    cards: CardRow[];
    filters: { type: string; visibility: string; q: string | null };
    counts: { public_characters: number; public_upgrades: number; total_characters: number; total_upgrades: number };
}>();

const typeFilter = ref(props.filters.type);
const visibilityFilter = ref(props.filters.visibility);
const search = ref(props.filters.q ?? '');

const apply = () => {
    router.get(
        route('admin.custom_cards.index'),
        {
            type: typeFilter.value,
            visibility: visibilityFilter.value,
            q: search.value || null,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
};

let searchTimer: ReturnType<typeof setTimeout> | null = null;
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(apply, 300);
});

const unpublish = async (card: CardRow) => {
    if (!(await confirm({
        title: `Force-unpublish "${card.name}"?`,
        message: 'The owner can re-publish it from their card creator.',
        confirmLabel: 'Unpublish',
    }))) return;
    router.post(route('admin.custom_cards.unpublish', { kind: card.kind, id: card.id }), {}, { preserveScroll: true });
};

const remove = async (card: CardRow) => {
    if (!(await confirm({
        title: `Soft-delete "${card.name}"?`,
        message: 'It will disappear from the public site. Recoverable via the database.',
        confirmLabel: 'Delete',
        destructive: true,
    }))) return;
    router.post(route('admin.custom_cards.delete', { kind: card.kind, id: card.id }), {}, { preserveScroll: true });
};

const formatDate = (s: string | null) => (s ? new Date(s).toLocaleDateString(undefined, { dateStyle: 'short' }) : '—');
</script>

<template>
    <Head title="Custom Cards - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex items-center gap-2">
            <ShieldAlert class="size-5" />
            <h1 class="text-2xl font-semibold tracking-tight">Custom Card Moderation</h1>
        </div>
        <p class="text-sm text-muted-foreground">User-created Characters and Upgrades. Force-unpublish hides from the public site without deleting.</p>

        <div class="grid gap-3 sm:grid-cols-4">
            <Card><CardContent class="p-3"><div class="text-[10px] uppercase text-muted-foreground">Public Characters</div><div class="text-xl font-bold">{{ counts.public_characters }}</div></CardContent></Card>
            <Card><CardContent class="p-3"><div class="text-[10px] uppercase text-muted-foreground">Public Upgrades</div><div class="text-xl font-bold">{{ counts.public_upgrades }}</div></CardContent></Card>
            <Card><CardContent class="p-3"><div class="text-[10px] uppercase text-muted-foreground">Total Characters</div><div class="text-xl font-bold">{{ counts.total_characters }}</div></CardContent></Card>
            <Card><CardContent class="p-3"><div class="text-[10px] uppercase text-muted-foreground">Total Upgrades</div><div class="text-xl font-bold">{{ counts.total_upgrades }}</div></CardContent></Card>
        </div>

        <Card>
            <CardContent class="flex flex-wrap items-end gap-3 p-4">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium uppercase text-muted-foreground">Type</label>
                    <Select v-model="typeFilter" @update:model-value="apply">
                        <SelectTrigger class="w-32"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            <SelectItem value="character">Characters</SelectItem>
                            <SelectItem value="upgrade">Upgrades</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium uppercase text-muted-foreground">Visibility</label>
                    <Select v-model="visibilityFilter" @update:model-value="apply">
                        <SelectTrigger class="w-32"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="public">Public</SelectItem>
                            <SelectItem value="private">Private</SelectItem>
                            <SelectItem value="all">All</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium uppercase text-muted-foreground">Search</label>
                    <Input v-model="search" placeholder="Name…" class="w-56" />
                </div>
            </CardContent>
        </Card>

        <div class="space-y-2">
            <Card v-for="card in cards" :key="`${card.kind}:${card.id}`">
                <CardContent class="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <a :href="card.share_url" target="_blank" rel="noopener" class="text-sm font-semibold hover:underline">{{ card.name }}</a>
                            <Badge variant="outline" class="text-[10px]">{{ card.kind }}</Badge>
                            <Badge v-if="card.faction" variant="secondary" class="text-[10px]">{{ card.faction }}</Badge>
                            <Badge v-if="card.domain" variant="secondary" class="text-[10px]">{{ card.domain }}</Badge>
                            <Badge v-if="!card.is_public" variant="outline" class="text-[10px] text-muted-foreground">private</Badge>
                        </div>
                        <div class="mt-0.5 text-xs text-muted-foreground">
                            <span v-if="card.user">by {{ card.user.name }}</span>
                            <span v-else class="italic">no owner</span>
                            · {{ formatDate(card.created_at) }}
                            · <code class="rounded bg-muted px-1">{{ card.share_code }}</code>
                        </div>
                    </div>
                    <div class="flex shrink-0 gap-1.5">
                        <Button variant="outline" size="sm" as-child>
                            <a :href="card.share_url" target="_blank" rel="noopener"><Eye class="size-3.5" /></a>
                        </Button>
                        <Button v-if="card.is_public" variant="outline" size="sm" @click="unpublish(card)" title="Force unpublish">
                            <EyeOff class="size-3.5" />
                        </Button>
                        <Button variant="outline" size="sm" @click="remove(card)" title="Soft-delete">
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </CardContent>
            </Card>
            <div v-if="!cards.length" class="py-12 text-center text-sm text-muted-foreground">No cards match your filters.</div>
        </div>
    </div>
</template>
