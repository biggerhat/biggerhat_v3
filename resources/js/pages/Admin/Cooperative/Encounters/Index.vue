<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface EncounterRow {
    id: number;
    number: number;
    name: string;
    encounter_size: number | null;
    max_hand_size: number | null;
    is_published: boolean;
}

defineProps<{ items: EncounterRow[] }>();
</script>

<template>
    <Head title="Cooperative Encounters — Admin" />

    <PageBanner title="Encounters" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Pg 80–131. Number 0 is the introduction. Unpublished encounters are hidden from the public pages.
            </div>
        </template>
        <template #actions>
            <Button class="my-auto mr-2" @click="router.get(route('admin.cooperative.encounters.create'))">Create</Button>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6">
        <EmptyState v-if="!items.length" compact title="No encounters yet" description="Use Create to add an encounter from the rulebook." />
        <div v-else class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-16">#</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Size</TableHead>
                        <TableHead>Hand</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in items" :key="row.id">
                        <TableCell class="tabular-nums">{{ row.number }}</TableCell>
                        <TableCell class="font-medium">{{ row.name }}</TableCell>
                        <TableCell class="tabular-nums">{{ row.encounter_size ?? '—' }}</TableCell>
                        <TableCell class="tabular-nums">{{ row.max_hand_size ?? '—' }}</TableCell>
                        <TableCell>
                            <Badge :variant="row.is_published ? 'secondary' : 'outline'" class="text-2xs">{{
                                row.is_published ? 'Published' : 'Draft'
                            }}</Badge>
                        </TableCell>
                        <TableCell>
                            <AdminActions
                                :name="row.name"
                                :edit-route="route('admin.cooperative.encounters.edit', row.id)"
                                :delete-route="route('admin.cooperative.encounters.delete', row.id)"
                            />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
