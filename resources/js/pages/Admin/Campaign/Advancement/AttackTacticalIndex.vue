<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface AdvancementRow {
    id: number;
    name: string;
    flip_value: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    is_always_available: boolean;
    modifier_type: string;
    suit: string | null;
    skl_from: number | null;
    skl_from_max: number | null;
    skl_to: number | null;
    trigger_id: number | null;
}

const props = defineProps<{
    items: AdvancementRow[];
    route_prefix: string;
    display_label: string;
}>();

const valueLabel = (r: AdvancementRow): string => {
    if (r.is_black_joker && r.is_red_joker) return 'Any Joker';
    if (r.is_black_joker) return 'Black Joker';
    if (r.is_red_joker) return 'Red Joker';
    if (r.is_always_available) return 'Always';
    return r.flip_value?.toString() ?? '—';
};

const modifierLabel: Record<string, string> = {
    trigger: 'Trigger',
    skl_boost: 'Skl Boost',
    signature: 'Signature',
};

// Suit only applies to trigger rows; skl_boost rows show their qualifying
// Skl range (or exact value) here instead — the two are mutually exclusive.
const suitOrSklCell = (r: AdvancementRow): string => {
    if (r.modifier_type !== 'skl_boost') return r.suit ?? '—';
    if (r.skl_from == null) return '—';
    const range = r.skl_from_max != null && r.skl_from_max !== r.skl_from ? `${r.skl_from}–${r.skl_from_max}` : `${r.skl_from}`;

    return `Skl ${range} → ${r.skl_to ?? '?'}`;
};
</script>

<template>
    <Head :title="`Campaign — ${display_label} Advancement — Admin`" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ display_label }} Advancements</h1>
                <p class="text-sm text-muted-foreground">Index of the Untold leader advancement table — flip &lt;= value picks an option.</p>
            </div>
            <Button @click="router.get(route(`${route_prefix}.create`))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Value</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Suit / Skl</TableHead>
                        <TableHead>Trigger Lookup</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="props.items.length">
                        <TableRow v-for="row in props.items" :key="row.id">
                            <TableCell class="tabular-nums">{{ valueLabel(row) }}</TableCell>
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="text-xs">{{ modifierLabel[row.modifier_type] ?? row.modifier_type }}</TableCell>
                            <TableCell class="text-xs">{{ suitOrSklCell(row) }}</TableCell>
                            <TableCell>
                                <Badge v-if="row.trigger_id" variant="outline" class="text-[10px]">#{{ row.trigger_id }}</Badge>
                                <span v-else class="text-xs text-muted-foreground">bespoke</span>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route(`${route_prefix}.edit`, row.id)"
                                    :delete-route="route(`${route_prefix}.delete`, row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell :colspan="6">
                            <EmptyState compact title="No rows yet" description="Use Create to seed from the rulebook." />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
