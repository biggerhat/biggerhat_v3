<script setup lang="ts">
import AdminActions from '@/components/AdminActions.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

interface Transmission {
    id: number;
    title: string;
    slug: string;
    url: string;
    transmission_type: string;
    content_type: string;
    channel: { id: number; name: string; slug: string } | null;
    factions: string[] | null;
    release_date: string | null;
}

const props = defineProps<{
    transmissions: {
        data: Transmission[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        first_page_url: string;
        last_page_url: string;
        next_page_url: string | null;
        prev_page_url: string | null;
        path: string;
        from: number | null;
        to: number | null;
    };
    filters: { q: string };
}>();

const search = ref(props.filters.q);

let debounce: ReturnType<typeof setTimeout> | undefined;
onBeforeUnmount(() => clearTimeout(debounce));

const onSearchInput = () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(
            route('admin.transmissions.index'),
            { q: search.value || undefined },
            { only: ['transmissions', 'filters'], replace: true, preserveState: true, preserveScroll: true },
        );
    }, 300);
};
</script>

<template>
    <Head title="Transmissions - Admin" />

    <PageBanner title="Transmissions" class="mb-2">
        <template #actions>
            <Button class="my-auto mr-2" @click="router.get(route('admin.transmissions.create'))"> Create New Transmission </Button>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6 h-full px-2">
        <div class="flex items-center justify-between py-4">
            <Input v-model="search" class="max-w-sm" placeholder="Search Transmissions" @input="onSearchInput" />
            <div>Total {{ transmissions.total }}</div>
        </div>
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Title</TableHead>
                        <TableHead>Channel</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Content</TableHead>
                        <TableHead>Factions</TableHead>
                        <TableHead>Release Date</TableHead>
                        <TableHead>Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="transmissions.data.length">
                        <TableRow v-for="t in transmissions.data" :key="t.id">
                            <TableCell>{{ t.title }}</TableCell>
                            <TableCell>{{ t.channel?.name ?? '-' }}</TableCell>
                            <TableCell class="capitalize">{{ t.transmission_type }}</TableCell>
                            <TableCell class="capitalize">{{ String(t.content_type).replace(/_/g, ' ') }}</TableCell>
                            <TableCell>{{ t.factions?.length ? t.factions.join(', ') : '-' }}</TableCell>
                            <TableCell>{{ t.release_date ?? '-' }}</TableCell>
                            <TableCell>
                                <AdminActions
                                    :name="t.title"
                                    :edit-route="route('admin.transmissions.edit', t.slug)"
                                    :delete-route="route('admin.transmissions.delete', t.slug)"
                                />
                            </TableCell>
                        </TableRow>
                    </template>
                    <TableRow v-else>
                        <TableCell colspan="7" class="h-24 text-center"> No results. </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <div class="py-4">
            <InertiaPagination :paginator="transmissions" :only="['transmissions']" />
        </div>
    </div>
</template>
