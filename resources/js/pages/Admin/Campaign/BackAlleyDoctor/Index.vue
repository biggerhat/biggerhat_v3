<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface DoctorRow {
    id: number;
    name: string;
    flip_value_min: number | null;
    flip_value_max: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    outcome_kind: string;
}

defineProps<{ items: DoctorRow[] }>();

const rangeLabel = (r: DoctorRow): string => {
    if (r.is_black_joker) return 'Black Joker';
    if (r.is_red_joker) return 'Red Joker';
    if (r.flip_value_min === r.flip_value_max) return String(r.flip_value_min ?? '');
    return `${r.flip_value_min ?? ''}–${r.flip_value_max ?? ''}`;
};
</script>

<template>
    <Head title="Campaign Back-Alley Doctor — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold">Back-Alley Doctor Results</h1>
                <p class="text-sm text-muted-foreground">Pg 33. Outcomes for the Phase 5 injury-removal flip.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.back-alley-doctor.create'))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Range</TableHead>
                        <TableHead>Outcome</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="items.length">
                        <TableRow v-for="row in items" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="tabular-nums">{{ rangeLabel(row) }}</TableCell>
                            <TableCell
                                ><Badge variant="outline" class="text-[10px]">{{ row.outcome_kind }}</Badge></TableCell
                            >
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.back-alley-doctor.edit', row.id)"
                                    :delete-route="route('admin.campaign.back-alley-doctor.delete', row.id)"
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
