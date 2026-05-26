<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface InjuryRow {
    id: number;
    name: string;
    flip_value: number | null;
    suit_pool: string;
    is_traitor: boolean;
    is_close_call: boolean;
    annihilates_model: boolean;
}

defineProps<{ items: InjuryRow[] }>();
</script>

<template>
    <Head title="Campaign Injuries — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold">Injury Catalog</h1>
                <p class="text-sm text-muted-foreground">Pg 34–35. 13 entries per suit pool (P/C, T/E) + 4 jokers.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.injuries.create'))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Pool</TableHead>
                        <TableHead>Flip</TableHead>
                        <TableHead>Flags</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="items.length">
                        <TableRow v-for="row in items" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="text-xs">{{ row.suit_pool }}</TableCell>
                            <TableCell class="tabular-nums">{{ row.flip_value ?? '—' }}</TableCell>
                            <TableCell>
                                <Badge v-if="row.is_traitor" variant="destructive" class="mr-1 text-[10px]">Traitor</Badge>
                                <Badge v-if="row.is_close_call" variant="outline" class="mr-1 text-[10px]">Close Call</Badge>
                                <Badge v-if="row.annihilates_model" variant="destructive" class="text-[10px]">Killed</Badge>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.injuries.edit', row.id)"
                                    :delete-route="route('admin.campaign.injuries.delete', row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="5" class="h-24 text-center text-sm text-muted-foreground">
                            No injuries yet. Use Create to seed from the rulebook.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
