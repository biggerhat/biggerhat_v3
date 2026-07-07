<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface CrewCardRow {
    id: number;
    name: string;
    requires_token_choice: boolean;
    requires_marker_choice: boolean;
    requires_upgrade_type_choice: boolean;
}

defineProps<{ items: CrewCardRow[] }>();
</script>

<template>
    <Head title="Campaign Crew Cards — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Crew Cards</h1>
                <p class="text-sm text-muted-foreground">Starting Crew Cards drawn by each player during arsenal setup (pg 15).</p>
            </div>
            <Button @click="router.get(route('admin.campaign.crew-cards.create'))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Flags</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="items.length">
                        <TableRow v-for="row in items" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell>
                                <Badge v-if="row.requires_token_choice" variant="outline" class="mr-1 text-[10px]">Token</Badge>
                                <Badge v-if="row.requires_marker_choice" variant="outline" class="mr-1 text-[10px]">Marker</Badge>
                                <Badge v-if="row.requires_upgrade_type_choice" variant="outline" class="text-[10px]">Upgrade Type</Badge>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.crew-cards.edit', row.id)"
                                    :delete-route="route('admin.campaign.crew-cards.delete', row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="3" class="h-24 text-center text-sm text-muted-foreground">No crew cards yet.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
