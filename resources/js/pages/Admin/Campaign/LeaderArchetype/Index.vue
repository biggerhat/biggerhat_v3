<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface Archetype {
    id: number;
    slug: string;
    name: string;
    df: number;
    wp: number;
    sp: number;
    health: number;
    attack_gets_trigger: boolean;
}

defineProps<{ archetypes: Archetype[] }>();
</script>

<template>
    <Head title="Campaign Leader Archetypes — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold">Leader Archetypes</h1>
                <p class="text-sm text-muted-foreground">5 archetypes from <em>Index of the Untold</em>, pg 17.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.leader-archetypes.create'))">Create Archetype</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead class="tabular-nums">Df</TableHead>
                        <TableHead class="tabular-nums">Wp</TableHead>
                        <TableHead class="tabular-nums">Sp</TableHead>
                        <TableHead class="tabular-nums">HP</TableHead>
                        <TableHead>Attack Trigger</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="archetypes.length">
                        <TableRow v-for="row in archetypes" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="tabular-nums">{{ row.df }}</TableCell>
                            <TableCell class="tabular-nums">{{ row.wp }}</TableCell>
                            <TableCell class="tabular-nums">{{ row.sp }}</TableCell>
                            <TableCell class="tabular-nums">{{ row.health }}</TableCell>
                            <TableCell class="text-xs text-muted-foreground">{{ row.attack_gets_trigger ? 'Yes' : 'No' }}</TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.leader-archetypes.edit', row.slug)"
                                    :delete-route="route('admin.campaign.leader-archetypes.delete', row.slug)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="7" class="h-24 text-center text-sm text-muted-foreground">
                            No archetypes yet. Use Create to seed the 5 from the rulebook.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
