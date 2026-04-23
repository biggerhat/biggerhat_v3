<script setup lang="ts">
import AlertMessage from '@/components/AlertMessage.vue';
import AppAdminSidebar from '@/components/AppAdminSidebar.vue';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import CookieConsent from '@/components/CookieConsent.vue';
import Toaster from '@/components/Toaster.vue';
import { useToast } from '@/composables/useToast';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const pageKey = computed(() => page.url);

const toast = useToast();
const flashMessage = computed(() => (page.props as { flash?: { message?: string | null } }).flash?.message ?? null);

watch(
    flashMessage,
    (next) => {
        if (!next) return;
        const flash = (page.props as { flash?: { message?: string; messageTitle?: string | null; messageType?: string | null } }).flash;
        toast.fromFlash((flash?.messageType as 'success' | 'info' | 'warn' | 'error' | 'default' | null) ?? 'default', next, {
            description: flash?.messageTitle ?? undefined,
        });
    },
    { immediate: true },
);

const hasResetLink = computed(() => !!(page.props as { flash?: { reset_link?: string | null } }).flash?.reset_link);
</script>

<template>
    <AppShell variant="sidebar">
        <AppAdminSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <AlertMessage
                v-if="hasResetLink"
                :message="(($page.props as { flash?: { reset_link?: string } }).flash?.reset_link) ?? ''"
                message-title="Password reset link"
                message-type="info"
                class="w-3/4"
            />
            <Transition name="page-fade" mode="out-in">
                <div :key="pageKey" class="flex flex-1 flex-col">
                    <slot />
                </div>
            </Transition>
        </AppContent>
        <CookieConsent />
        <Toaster />
    </AppShell>
</template>
