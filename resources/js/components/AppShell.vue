<script setup lang="ts">
import AnnouncementBanner from '@/components/AnnouncementBanner.vue';
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import ImpersonationBanner from '@/components/ImpersonationBanner.vue';
import PromptDialog from '@/components/PromptDialog.vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { onMounted, ref } from 'vue';

interface Props {
    variant?: 'header' | 'sidebar';
}

defineProps<Props>();

const isOpen = ref(true);

onMounted(() => {
    isOpen.value = localStorage.getItem('sidebar') !== 'false';
});

const handleSidebarChange = (open: boolean) => {
    isOpen.value = open;
    localStorage.setItem('sidebar', String(open));
};
</script>

<template>
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-background focus:px-3 focus:py-2 focus:text-sm focus:font-medium focus:shadow focus:ring-2 focus:ring-ring"
    >
        Skip to main content
    </a>
    <ImpersonationBanner />
    <AnnouncementBanner />
    <ConfirmDialog />
    <PromptDialog />
    <div v-if="variant === 'header'" class="flex min-h-screen w-full flex-col">
        <slot />
    </div>
    <SidebarProvider v-else :default-open="isOpen" :open="isOpen" @update:open="handleSidebarChange">
        <slot />
    </SidebarProvider>
</template>
