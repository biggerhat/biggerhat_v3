<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface EquipmentRow {
    id: number;
    name: string;
    br: number | null;
    cc: number;
    is_always_available: boolean;
    ttw_only: boolean;
    is_omens_mark: boolean;
    pool_suit_a: string | null;
    pool_suit_b: string | null;
    is_unique: boolean;
    leader_only: boolean;
}

const props = defineProps<{ items: EquipmentRow[] }>();
const filter = ref('');
const filtered = computed(() => props.items.filter((r) => !filter.value || r.name.toLowerCase().includes(filter.value.toLowerCase())));

const brLabel = (r: EquipmentRow): string => {
    if (r.is_always_available) return 'Always';
    return r.br?.toString() ?? '—';
};

const poolLabel = (r: EquipmentRow): string => {
    if (r.is_always_available) return '—';
    return [r.pool_suit_a, r.pool_suit_b].filter(Boolean).join(' / ');
};
</script>

<template>
    <Head title="Campaign Equipment — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between gap-3 py-4">
            <div>
                <h1 class="text-2xl font-semibold">Equipment Catalog</h1>
                <p class="text-sm text-muted-foreground">Index of the Untold pg 22–28 (82 base) + Those Who Thirst (pg 29–30) + Omen's Mark.</p>
            </div>
            <div class="flex items-center gap-2">
                <Input v-model="filter" placeholder="Filter by name" class="w-64" />
                <Button @click="router.get(route('admin.campaign.equipment.create'))">Create</Button>
            </div>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>BR</TableHead>
                        <TableHead>CC</TableHead>
                        <TableHead>Pool</TableHead>
                        <TableHead>Flags</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="filtered.length">
                        <TableRow v-for="row in filtered" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="tabular-nums">{{ brLabel(row) }}</TableCell>
                            <TableCell class="tabular-nums">{{ row.cc }}</TableCell>
                            <TableCell class="text-xs">{{ poolLabel(row) }}</TableCell>
                            <TableCell>
                                <Badge v-if="row.ttw_only" variant="destructive" class="mr-1 text-[10px]">TTW</Badge>
                                <Badge v-if="row.is_omens_mark" variant="destructive" class="mr-1 text-[10px]">Omen</Badge>
                                <Badge v-if="row.is_unique" variant="outline" class="mr-1 text-[10px]">Unique</Badge>
                                <Badge v-if="row.leader_only" variant="outline" class="text-[10px]">Leader</Badge>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.equipment.edit', row.id)"
                                    :delete-route="route('admin.campaign.equipment.delete', row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="6" class="h-24 text-center text-sm text-muted-foreground">
                            No equipment yet. Use Create to seed from the rulebook.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
