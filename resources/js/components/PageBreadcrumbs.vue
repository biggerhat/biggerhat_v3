<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import JsonLd from '@/components/JsonLd.vue';
import { deriveBreadcrumbs } from '@/lib/breadcrumbs';
import type { BreadcrumbItemType } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    /** Explicit trail from the page; when empty the trail is derived from the page component + props. */
    breadcrumbs?: BreadcrumbItemType[];
}>();

const page = usePage();

const trail = computed(() => (props.breadcrumbs?.length ? props.breadcrumbs : deriveBreadcrumbs(page.component, page.props)));

/** On phones only the way back up is shown: "‹ Keywords", not a wrapping four-level trail. */
const parent = computed(() => (trail.value.length > 1 ? trail.value[trail.value.length - 2] : null));

const jsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: trail.value.map((item, index) => ({
        '@type': 'ListItem',
        position: index + 1,
        name: item.title,
        // The current page has no href; Google accepts omitting `item` on the last element.
        ...(item.href ? { item: new URL(item.href, (page.props as { ziggy?: { location?: string } }).ziggy?.location).toString() } : {}),
    })),
}));
</script>

<template>
    <div v-if="trail.length > 1" class="border-b border-border/60 px-4 py-2 sm:px-6 md:px-4">
        <!-- Phones: just the parent, as a back link. -->
        <Link
            v-if="parent?.href"
            :href="parent.href"
            class="inline-flex min-h-8 items-center gap-1 text-sm text-muted-foreground hover:text-foreground sm:hidden"
        >
            <ChevronLeft class="size-4" aria-hidden="true" />
            {{ parent.title }}
        </Link>
        <Breadcrumbs :breadcrumbs="trail" class="hidden sm:block" />
        <JsonLd head-key="breadcrumbs" :data="jsonLd" />
    </div>
</template>
