<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface EventRow {
    id: number;
    name: string;
    flip_value: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    requires_placement: boolean;
    is_one_time: boolean;
}

defineProps<{ items: EventRow[] }>();

const flipLabel = (r: EventRow): string => {
    if (r.is_black_joker) return 'Black Joker';
    if (r.is_red_joker) return 'Red Joker';
    return String(r.flip_value ?? '—');
};
</script>

<template>
    <Head title="Campaign Weekly Events — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Weekly Events</h1>
                <p class="text-sm text-muted-foreground">Pg 148–149. Rolled at week start when weekly events are enabled.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.weekly-events.create'))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Flip</TableHead>
                        <TableHead>Flags</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="items.length">
                        <TableRow v-for="row in items" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="tabular-nums">{{ flipLabel(row) }}</TableCell>
                            <TableCell>
                                <Badge v-if="row.requires_placement" variant="outline" class="mr-1 text-[10px]">Placement</Badge>
                                <Badge v-if="row.is_one_time" variant="destructive" class="text-[10px]">One-time</Badge>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.weekly-events.edit', row.id)"
                                    :delete-route="route('admin.campaign.weekly-events.delete', row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="4" class="h-24 text-center text-sm text-muted-foreground">No rows yet.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
