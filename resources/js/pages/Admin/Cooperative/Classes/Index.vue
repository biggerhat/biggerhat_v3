<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface ClassRow {
    id: number;
    name: string;
    role: string;
    is_unique: boolean;
    resistance: number;
    speed: number | null;
    max_health: number;
    elite_max_health: number | null;
    is_published: boolean;
}

const props = defineProps<{ items: ClassRow[]; roles: { name: string; value: string }[] }>();
const search = ref('');
const roleLabel = (value: string) => props.roles.find((r) => r.value === value)?.name ?? value;
const visible = computed(() => {
    const q = search.value.trim().toLowerCase();
    return q ? props.items.filter((i) => i.name.toLowerCase().includes(q) || roleLabel(i.role).toLowerCase().includes(q)) : props.items;
});
</script>

<template>
    <Head title="Cooperative NP Classes — Admin" />

    <PageBanner title="NP Classes" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Pg 132–137. Enemy class cards for Cooperative Mode.
            </div>
        </template>
        <template #actions>
            <Button class="my-auto mr-2" @click="router.get(route('admin.cooperative.classes.create'))">Create</Button>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6 space-y-3">
        <Input v-model="search" class="max-w-sm" placeholder="Filter by name or role..." aria-label="Filter classes" />
        <EmptyState v-if="!items.length" compact title="No classes yet" description="Use Create to add the class cards from the rulebook." />
        <div v-else class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Res / Sp</TableHead>
                        <TableHead>Health</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in visible" :key="row.id">
                        <TableCell class="font-medium">
                            {{ row.name }} <Badge v-if="row.is_unique" variant="outline" class="ml-1 text-2xs">Unique</Badge>
                        </TableCell>
                        <TableCell>{{ roleLabel(row.role) }}</TableCell>
                        <TableCell class="tabular-nums">{{ row.resistance }} / {{ row.speed ?? '—' }}</TableCell>
                        <TableCell class="tabular-nums"
                            >{{ row.max_health }}<span v-if="row.elite_max_health"> ({{ row.elite_max_health }})</span></TableCell
                        >
                        <TableCell>
                            <Badge :variant="row.is_published ? 'secondary' : 'outline'" class="text-2xs">{{
                                row.is_published ? 'Published' : 'Draft'
                            }}</Badge>
                        </TableCell>
                        <TableCell>
                            <AdminActions
                                :name="row.name"
                                :edit-route="route('admin.cooperative.classes.edit', row.id)"
                                :delete-route="route('admin.cooperative.classes.delete', row.id)"
                            />
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
