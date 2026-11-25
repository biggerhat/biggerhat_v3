<script setup lang="ts">
import { sortableHeader } from '@/lib/adminTable';
import AdminActions from '@/components/AdminActions.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { valueUpdater } from '@/lib/utils';
import { Head, router } from '@inertiajs/vue3';
import type { ColumnDef, ColumnFiltersState } from '@tanstack/vue-table';
import { FlexRender, getCoreRowModel, getSortedRowModel, getFilteredRowModel, getPaginationRowModel, useVueTable } from '@tanstack/vue-table';
import { Send } from 'lucide-vue-next';
import { h, ref } from 'vue';

interface BlogPost {
    id: number;
    title: string;
    slug: string;
    status: string;
    author: { name: string } | null;
    category: { name: string } | null;
    published_at: string | null;
    discord_posted_at?: string | null;
    created_at: string;
}

const props = defineProps<{
    posts: BlogPost[];
    postType?: 'blog' | 'news';
    /** News only: the site has a Discord channel set up, so a published post can be sent to it. */
    discord_ready?: boolean;
}>();

const routeFor = (suffix: string, param?: string) => route(`admin.${props.postType ?? 'blog'}.posts.${suffix}`, param);

const statusVariant = (status: string) => {
    switch (status) {
        case 'published':
            return 'default';
        case 'draft':
            return 'secondary';
        case 'archived':
            return 'outline';
        default:
            return 'secondary';
    }
};

const columns: ColumnDef<BlogPost>[] = [
    {
        accessorKey: 'title',
        header: sortableHeader('Title'),
        cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('title')),
    },
    {
        accessorKey: 'category',
        header: sortableHeader('Category'),
        cell: ({ row }) => {
            const cat = row.original.category;
            return h('div', {}, cat?.name ?? '—');
        },
    },
    {
        accessorKey: 'status',
        header: sortableHeader('Status'),
        cell: ({ row }) => {
            const status = row.getValue('status') as string;
            return h(Badge, { variant: statusVariant(status) }, () => status);
        },
    },
    {
        accessorKey: 'author',
        header: sortableHeader('Author'),
        cell: ({ row }) => h('div', {}, row.original.author?.name ?? '—'),
    },
    {
        accessorKey: 'created_at',
        header: sortableHeader('Date'),
        cell: ({ row }) => {
            const date = row.original.published_at ?? row.original.created_at;
            return h('div', {}, new Date(date).toLocaleDateString());
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => h('div', { class: 'text-right' }, 'Actions'),
        cell: ({ row }) => {
            const post = row.original;
            const canPost = props.postType === 'news' && props.discord_ready && post.status === 'published';
            return h('div', { class: 'relative flex items-center justify-end gap-1' }, [
                canPost
                    ? h(
                          Button,
                          {
                              variant: 'ghost',
                              size: 'sm',
                              class: 'h-8 gap-1 px-2 text-xs',
                              'data-testid': 'news-discord',
                              title: post.discord_posted_at
                                  ? `Posted to Discord ${new Date(post.discord_posted_at).toLocaleDateString()}`
                                  : 'Post to Discord',
                              onClick: () => {
                                  discordMessage.value = '';
                                  discordPost.value = post;
                              },
                          },
                          () => [h(Send, { class: 'size-3.5' }), post.discord_posted_at ? 'Posted' : 'Post to Discord'],
                      )
                    : null,
                h(AdminActions, {
                    name: post.title,
                    previewRoute: routeFor('preview', post.slug),
                    editRoute: routeFor('edit', post.slug),
                    deleteRoute: routeFor('delete', post.slug),
                }),
            ]);
        },
    },
];

// Post to Discord (news): an optional line of your own goes above the post.
const discordPost = ref<BlogPost | null>(null);
const discordMessage = ref('');
const discordSending = ref(false);
const sendToDiscord = () => {
    const post = discordPost.value;
    if (!post) return;
    discordSending.value = true;
    router.post(
        routeFor('discord', post.slug),
        { message: discordMessage.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => (discordPost.value = null),
            onFinish: () => (discordSending.value = false),
        },
    );
};

const columnFilters = ref<ColumnFiltersState>([]);

const table = useVueTable({
    get data() {
        return props.posts;
    },
    get columns() {
        return columns;
    },
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    initialState: { pagination: { pageSize: 50 } },
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
    <Head :title="postType === 'news' ? 'Site News - Admin' : 'Articles - Admin'" />

    <PageBanner :title="postType === 'news' ? 'Site News' : 'Articles'" class="mb-2">
        <template #actions>
            <Button class="my-auto mr-2" @click="router.get(routeFor('create'))">Create New {{ postType === 'news' ? 'News Post' : 'Post' }}</Button>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6 h-full">
        <div class="flex items-center justify-between py-4">
            <Input
                class="max-w-sm"
                placeholder="Filter Posts"
                :model-value="table.getColumn('title')?.getFilterValue() as string"
                @update:model-value="table.getColumn('title')?.setFilterValue($event)"
            />
            <div>Total {{ props.posts.length }}</div>
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
                            <TableCell :colspan="columns.length" class="h-24 text-center">No results.</TableCell>
                        </TableRow>
                    </template>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-end space-x-2 py-4">
            <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()" @click="table.previousPage()">Previous</Button>
            <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()">Next</Button>
        </div>
    </div>

    <Dialog :open="discordPost !== null" @update:open="(v) => !v && (discordPost = null)">
        <DialogContent data-testid="news-discord-dialog">
            <DialogHeader>
                <DialogTitle>Post to Discord</DialogTitle>
                <DialogDescription>
                    Sends "{{ discordPost?.title }}" to the site's Discord channel with a link.
                    <template v-if="discordPost?.discord_posted_at">
                        It was already posted on {{ new Date(discordPost.discord_posted_at).toLocaleDateString() }}; posting again adds another
                        message.
                    </template>
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-1.5">
                <label for="discord-message" class="text-sm font-medium">A line of your own (optional)</label>
                <Textarea id="discord-message" v-model="discordMessage" maxlength="300" rows="3" placeholder="Shown above the post." />
            </div>
            <DialogFooter>
                <Button variant="outline" @click="discordPost = null">Cancel</Button>
                <Button :disabled="discordSending" data-testid="news-discord-send" @click="sendToDiscord">Post</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
