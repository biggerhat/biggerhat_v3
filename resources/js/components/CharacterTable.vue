<script setup lang="ts">
import { csrfToken, valueUpdater } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { BookMarked, Heart, Plus } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

import CharacterTableLink from '@/components/CharacterTableLink.vue';
import EmptyState from '@/components/EmptyState.vue';
import { FlexRender, getCoreRowModel, getFilteredRowModel, useVueTable } from '@tanstack/vue-table';

const page = usePage<SharedData>();

const collectionIds = computed(() => new Set(page.props.auth?.collection_miniature_ids ?? []));
const wishlistMiniatureIds = computed(() => {
    const ids = new Set<number>();
    for (const wl of Object.values(page.props.auth?.wishlist_items ?? {})) {
        for (const id of wl.miniatures) ids.add(id);
    }
    return ids;
});

const isCollected = (character: any) => {
    return (character.standard_miniatures ?? []).some((m: any) => collectionIds.value.has(m.id));
};

const isWishlisted = (character: any) => {
    return (character.standard_miniatures ?? []).some((m: any) => wishlistMiniatureIds.value.has(m.id));
};

const isLoggedIn = computed(() => !!page.props.auth?.user);

const addToCollection = async (character: any) => {
    // Optimistically update shared auth data
    const ids = page.props.auth.collection_miniature_ids;
    for (const m of character.standard_miniatures ?? []) {
        if (!ids.includes(m.id)) ids.push(m.id);
    }

    await fetch(route('collection.add_character'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({ character_id: character.id }),
    });
};

const columns: ColumnDef<Miniatures>[] = [
    {
        accessorKey: 'display_name',
        header: () => h('div', {}, 'Name'),
        cell: ({ row }) => {
            const character = row.original;
            const children = [h(CharacterTableLink, { character: character })];
            if (isCollected(character)) {
                children.push(h(BookMarked, { class: 'inline size-3.5', style: 'color: #059669', title: 'In Collection' }));
            } else if (isLoggedIn.value) {
                children.push(
                    h(
                        'button',
                        {
                            class: 'inline-flex size-5 items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                            title: 'Add to Collection',
                            onClick: (e: Event) => {
                                e.preventDefault();
                                addToCollection(character);
                            },
                        },
                        h(Plus, { class: 'size-3.5' }),
                    ),
                );
            }
            if (isWishlisted(character)) {
                children.push(h(Heart, { class: 'inline size-3.5 fill-current', style: 'color: #f43f5e', title: 'On Wishlist' }));
            }
            return h('div', { class: 'flex w-auto items-center gap-1.5' }, children);
        },
    },
    {
        accessorKey: 'cost',
        header: () => h('div', { class: 'text-center' }, 'Cost'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('cost'));
        },
    },
    {
        accessorKey: 'health',
        header: () => h('div', { class: 'text-center' }, 'Health'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('health'));
        },
    },
    {
        accessorKey: 'speed',
        header: () => h('div', { class: 'text-center' }, 'Speed'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('speed'));
        },
    },
    {
        accessorKey: 'defense',
        header: () => h('div', { class: 'text-center' }, 'Defense'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('defense'));
        },
    },
    {
        accessorKey: 'willpower',
        header: () => h('div', { class: 'text-center' }, 'Willpower'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('willpower'));
        },
    },
    {
        accessorKey: 'size',
        header: () => h('div', { class: 'text-center' }, 'Size'),
        cell: ({ row }) => {
            return h('div', { class: 'text-center' }, row.getValue('size'));
        },
    },
];

const props = defineProps<{
    characters: TData[];
}>();

const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() {
        return props.characters;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    // getPaginationRowModel: getPaginationRowModel(),
    onColumnFiltersChange: (updaterOrValue) => valueUpdater(updaterOrValue, columnFilters),
    getFilteredRowModel: getFilteredRowModel(),
    state: {
        get columnFilters() {
            return columnFilters.value;
        },
    },
});
</script>

<template>
    <div class="mx-auto">
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
                            <TableCell :colspan="columns.length">
                                <EmptyState />
                            </TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
