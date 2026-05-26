<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface EffectRow {
    id: number;
    name: string;
    requires_token_choice: boolean;
    requires_marker_choice: boolean;
    requires_upgrade_type_choice: boolean;
}

defineProps<{ items: EffectRow[] }>();

const choicesLabel = (r: EffectRow): string => {
    const c = [];
    if (r.requires_token_choice) c.push('token');
    if (r.requires_marker_choice) c.push('marker');
    if (r.requires_upgrade_type_choice) c.push('upgrade type');
    return c.length ? `Needs: ${c.join(', ')}` : '—';
};
</script>

<template>
    <Head title="Campaign Crew Card Effects — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold">Crew Card Effects</h1>
                <p class="text-sm text-muted-foreground">13 starter effects (pg 15–16) + Tier-4 borrow targets.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.crew-card-effects.create'))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Choice Requirements</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="items.length">
                        <TableRow v-for="row in items" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="text-xs text-muted-foreground">{{ choicesLabel(row) }}</TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.crew-card-effects.edit', row.id)"
                                    :delete-route="route('admin.campaign.crew-card-effects.delete', row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="3" class="h-24 text-center text-sm text-muted-foreground">No rows yet.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
