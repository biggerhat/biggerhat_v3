<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface LuckyMissRow {
    id: number;
    name: string;
    flip_value: number | null;
    is_doppelganger: boolean;
}

defineProps<{ items: LuckyMissRow[] }>();
</script>

<template>
    <Head title="Campaign Lucky Miss — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Lucky Miss</h1>
                <p class="text-sm text-muted-foreground">Pg 36. Positive upgrades flipped on red-joker injuries.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.lucky-miss.create'))">Create</Button>
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
                            <TableCell class="tabular-nums">{{ row.flip_value ?? 'Joker' }}</TableCell>
                            <TableCell>
                                <Badge v-if="row.is_doppelganger" variant="outline" class="text-[10px]">Doppelganger</Badge>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.lucky-miss.edit', row.id)"
                                    :delete-route="route('admin.campaign.lucky-miss.delete', row.id)"
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
