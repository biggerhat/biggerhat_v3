<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface AdvancementActionRow {
    id: number;
    flip_value: number | null;
    is_joker: boolean;
    is_always_available: boolean;
    talent_name: string;
    action_id: number | null;
    action: { id: number; name: string } | null;
}

const props = defineProps<{
    items: AdvancementActionRow[];
}>();

const valueLabel = (r: AdvancementActionRow): string => {
    if (r.is_joker) return 'Any Joker';
    if (r.is_always_available) return 'Always';
    return r.flip_value?.toString() ?? '—';
};
</script>

<template>
    <Head title="Campaign — Action Advancement — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Action Advancements</h1>
                <p class="text-sm text-muted-foreground">Index of the Untold leader advancement table — flip &lt;= value picks an option.</p>
            </div>
            <Button @click="router.get(route('admin.campaign.advancement-action.create'))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Value</TableHead>
                        <TableHead>Talent Name</TableHead>
                        <TableHead>Action Lookup</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="props.items.length">
                        <TableRow v-for="row in props.items" :key="row.id">
                            <TableCell class="tabular-nums">{{ valueLabel(row) }}</TableCell>
                            <TableCell class="font-medium">{{ row.talent_name }}</TableCell>
                            <TableCell>
                                <Badge v-if="row.action" variant="outline" class="text-[10px]">{{ row.action.name }}</Badge>
                                <span v-else class="text-xs text-muted-foreground">bespoke</span>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.talent_name"
                                    :edit-route="route('admin.campaign.advancement-action.edit', row.id)"
                                    :delete-route="route('admin.campaign.advancement-action.delete', row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="4" class="h-24 text-center text-sm text-muted-foreground">
                            No rows yet. Use Create to seed from the rulebook.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
