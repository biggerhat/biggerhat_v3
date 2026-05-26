<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';

interface AdvancementRow {
    id: number;
    name: string;
    flip_value: number | null;
    is_always_available: boolean;
    is_black_joker: boolean;
    is_red_joker: boolean;
    modifier_type: string;
    suit: string | null;
    grants_signature: boolean;
    joker_freechoice: boolean;
}

const props = defineProps<{
    items: AdvancementRow[];
    route_prefix: string;
    display_label: string;
}>();

const flipLabel = (r: AdvancementRow): string => {
    if (r.is_black_joker) return 'Black Joker';
    if (r.is_red_joker) return 'Red Joker';
    if (r.is_always_available) return 'Always';
    return r.flip_value?.toString() ?? '—';
};
</script>

<template>
    <Head :title="`Campaign — ${display_label} Advancements — Admin`" />
    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ display_label }} Advancements</h1>
                <p class="text-sm text-muted-foreground">Index of the Untold leader advancement table — flip ≤ value picks an option.</p>
            </div>
            <Button @click="router.get(route(`${route_prefix}.create`))">Create</Button>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Flip</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Suit</TableHead>
                        <TableHead>Flags</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="props.items.length">
                        <TableRow v-for="row in props.items" :key="row.id">
                            <TableCell class="font-medium">{{ row.name }}</TableCell>
                            <TableCell class="tabular-nums">{{ flipLabel(row) }}</TableCell>
                            <TableCell class="text-xs">{{ row.modifier_type }}</TableCell>
                            <TableCell class="text-xs">{{ row.suit ?? '—' }}</TableCell>
                            <TableCell>
                                <Badge v-if="row.grants_signature" variant="outline" class="mr-1 text-[10px]">Signature</Badge>
                                <Badge v-if="row.joker_freechoice" variant="outline" class="text-[10px]">Free Pick</Badge>
                            </TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="row.name"
                                    :edit-route="route(`${route_prefix}.edit`, row.id)"
                                    :delete-route="route(`${route_prefix}.delete`, row.id)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="6" class="h-24 text-center text-sm text-muted-foreground">
                            No rows yet. Use Create to seed from the rulebook.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
