<script setup lang="ts">
import AppLayout from '@/layouts/app/AppAdminSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1">
            <slot />
        </div>
    </AppLayout>
</template>
