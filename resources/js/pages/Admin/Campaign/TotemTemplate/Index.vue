<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface TotemRow {
    id: number;
    name: string;
    faction: string;
    campaign_totem_flip_value: number | null;
    campaign_is_black_joker_totem: boolean;
    campaign_is_red_joker_totem: boolean;
    campaign_totem_special_replace: boolean;
}

defineProps<{ items: TotemRow[] }>();
</script>

<template>
    <Head title="Totem Templates — Admin" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold">Totem Templates</h1>
                <p class="text-sm text-muted-foreground">Campaign totem options drawn by flip value during Tier-3 Totem Advancement (pg 52).</p>
            </div>
            <Button @click="router.get(route('admin.campaign.totem-templates.create'))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Faction</TableHead>
                        <TableHead>Flip Value</TableHead>
                        <TableHead>Flags</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="items.length">
                        <TableRow v-for="row in items" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="capitalize">{{ row.faction }}</TableCell>
                            <TableCell>
                                <span v-if="row.campaign_is_black_joker_totem" class="font-medium">Black Joker</span>
                                <span v-else-if="row.campaign_is_red_joker_totem" class="font-medium">Red Joker</span>
                                <span v-else>{{ row.campaign_totem_flip_value ?? '—' }}</span>
                            </TableCell>
                            <TableCell>
                                <Badge v-if="row.campaign_totem_special_replace" variant="outline" class="text-[10px]">Special Replace</Badge>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route('admin.campaign.totem-templates.edit', row.id)"
                                    :delete-route="route('admin.campaign.totem-templates.delete', row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="5" class="h-24 text-center text-sm text-muted-foreground">No totem templates yet.</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
