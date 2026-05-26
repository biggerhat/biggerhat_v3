<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface TotemRow {
    id: number;
    name: string;
    flip_value: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    df: number;
    wp: number;
    sp: number;
    health: number;
    is_mini_master: boolean;
}

defineProps<{ items: TotemRow[] }>();

const flipLabel = (r: TotemRow): string => {
    if (r.is_black_joker) return 'Black Joker';
    if (r.is_red_joker) return 'Red Joker';
    return String(r.flip_value ?? '—');
};
</script>

<template>
    <Head title="Campaign Totems — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold">Totem Catalog</h1>
                <p class="text-sm text-muted-foreground">Pg 52–53. Tier-3 totem unlocks (exact-flip).</p>
            </div>
            <Button @click="router.get(route('admin.campaign.totems.create'))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Flip</TableHead>
                        <TableHead>Stats (Df/Wp/Sp/HP)</TableHead>
                        <TableHead>Flags</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="items.length">
                        <TableRow v-for="row in items" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="tabular-nums">{{ flipLabel(row) }}</TableCell>
                            <TableCell class="text-xs tabular-nums">{{ row.df }}/{{ row.wp }}/{{ row.sp }}/{{ row.health }}</TableCell>
                            <TableCell>
                                <Badge v-if="row.is_mini_master" variant="outline" class="text-[10px]">Mini-Master</Badge>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.totems.edit', row.id)"
                                    :delete-route="route('admin.campaign.totems.delete', row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="5" class="h-24 text-center text-sm text-muted-foreground">No rows yet.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
