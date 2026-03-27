<script setup lang="ts">
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationFirst,
    PaginationItem,
    PaginationLast,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Paginator {
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
}

const props = withDefaults(
    defineProps<{
        paginator: Paginator;
        only?: string[];
    }>(),
    {
        only: undefined,
    },
);

const showPagination = computed(() => props.paginator.last_page > 1);

const goToPage = (page: number) => {
    const url = new URL(window.location.href);
    if (page === 1) {
        url.searchParams.delete('page');
    } else {
        url.searchParams.set('page', String(page));
    }
    const options: Record<string, unknown> = { preserveState: true, preserveScroll: true, replace: true };
    if (props.only) {
        options.only = props.only;
    }
    router.get(url.pathname + url.search, {}, options);
};
</script>

<template>
    <div v-if="showPagination" class="flex flex-col items-center gap-2 py-6">
        <Pagination
            v-slot="{ page }"
            :total="paginator.total"
            :items-per-page="paginator.per_page"
            :default-page="paginator.current_page"
            :sibling-count="1"
            show-edges
            @update:page="goToPage"
        >
            <PaginationContent>
                <PaginationFirst />
                <PaginationPrevious />

                <template v-for="(item, index) in page.items" :key="item.type + '-' + item.value">
                    <PaginationItem v-if="item.type === 'page'" :value="item.value" :is-active="item.value === paginator.current_page">
                        {{ item.value }}
                    </PaginationItem>
                    <PaginationEllipsis v-else :index="index" />
                </template>

                <PaginationNext />
                <PaginationLast />
            </PaginationContent>
        </Pagination>
        <p class="text-xs text-muted-foreground">Showing {{ paginator.from }}-{{ paginator.to }} of {{ paginator.total }}</p>
    </div>
</template>
