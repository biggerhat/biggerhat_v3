<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useConfirm } from '@/composables/useConfirm';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import type { ColumnDef } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';
import { UserCog } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

const page = usePage<SharedData>();
const isSuperAdmin = computed(() => !!page.props.auth.is_super_admin);
const confirm = useConfirm();

const startImpersonation = async (user: { id: number; name: string }) => {
    if (!(await confirm({
        title: `Impersonate ${user.name}?`,
        message: `You'll be logged in as ${user.name} until you click "Leave Impersonation" in the banner at the top of the page.`,
        confirmLabel: 'Impersonate',
    }))) return;
    window.location.href = `/impersonate/take/${user.id}`;
};

const columns: ColumnDef<any>[] = [
    {
        accessorKey: 'name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('name'));
        },
    },
    {
        accessorKey: 'email',
        header: () => h('div', {}, 'Email'),
        cell: ({ row }) => {
            return h('div', {}, row.getValue('email'));
        },
    },
    {
        accessorKey: 'roles',
        enableGlobalFilter: false,
        header: () => h('div', {}, 'Roles'),
        cell: ({ row }) => {
            const roles = row.original.roles;

            return h(
                'div',
                { class: 'flex gap-1' },
                roles.map((role: any) => h(Badge, { variant: 'secondary' }, () => role.name)),
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        enableGlobalFilter: false,
        header: () => h('div', {}, 'Actions'),
        cell: ({ row }) => {
            const user = row.original;
            const userIsSuperAdmin = (user.roles ?? []).some((r: any) => r.name === 'super_admin');

            return h('div', { class: 'flex items-center gap-2' }, [
                // Impersonate button — super_admin only, can't take other super_admins.
                isSuperAdmin.value && !userIsSuperAdmin
                    ? h(
                          Button,
                          {
                              variant: 'outline',
                              size: 'sm',
                              title: `Impersonate ${user.name}`,
                              onClick: () => startImpersonation(user),
                          },
                          () => [h(UserCog, { class: 'size-4' })],
                      )
                    : null,
                h(AdminActions, {
                    name: user.name,
                    editRoute: route('admin.users.edit', user.slug),
                    deleteRoute: route('admin.users.delete', user.slug),
                }),
            ]);
        },
    },
];

const props = defineProps<{
    users: any[];
}>();

const globalFilter = ref('');

const table = useVueTable({
    get data() {
        return props.users;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    globalFilterFn: 'includesString',
    state: {
        get globalFilter() {
            return globalFilter.value;
        },
    },
});
</script>

<template>
    <Head title="Users - Admin" />

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input class="max-w-sm" placeholder="Filter Users" v-model="globalFilter" />
            <div>Total {{ props.users.length }}</div>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead v-for="header in headerGroup.headers" :key="header.id">
                            <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <TableRow v-for="row in table.getRowModel().rows" :key="row.id" :data-state="row.getIsSelected() ? 'selected' : undefined">
                            <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                            </TableCell>
                        </TableRow>
                    </template>
                    <template v-else>
                        <TableRow>
                            <TableCell :colspan="columns.length" class="h-24 text-center"> No results. </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-end space-x-2 py-4">
            <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()"> Previous </Button>
            <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()"> Next </Button>
        </div>
    </div>
</template>
