<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface SummoningRow {
    id: number;
    name: string;
}

defineProps<{ items: SummoningRow[] }>();
</script>

<template>
    <Head title="Campaign Summoning Advancements — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold">Summoning Advancements</h1>
                <p class="text-sm text-muted-foreground">Pg 54. Tier-3 free-pick advancements (max one per leader).</p>
            </div>
            <Button @click="router.get(route('admin.campaign.summoning-advancements.create'))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="items.length">
                        <TableRow v-for="row in items" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.summoning-advancements.edit', row.id)"
                                    :delete-route="route('admin.campaign.summoning-advancements.delete', row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="2" class="h-24 text-center text-sm text-muted-foreground">No rows yet.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
