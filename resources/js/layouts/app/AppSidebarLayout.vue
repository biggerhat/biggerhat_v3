<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import type { BreadcrumbItemType } from '@/types';
import AlertMessage from "@/components/AlertMessage.vue";

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <AlertMessage v-if="$page.props.flash?.message" :message="$page.props.flash.message" :message-title="$page.props.flash.messageTitle ?? null" :message-type="$page.props.flash.messageType ?? null" />
            <slot />
        </AppContent>
    </AppShell>
</template>
