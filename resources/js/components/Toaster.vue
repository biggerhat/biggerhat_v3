<script setup lang="ts">
/**
 * Thin wrapper around vue-sonner. Handles theme sync + consistent defaults so
 * every toast across the app looks the same.
 *
 * Imported once from AppSidebarLayout. Individual pages call toast.*() from
 * vue-sonner directly, or use the useToast() composable for typed helpers.
 */
import { useAppearance } from '@/composables/useAppearance';
import { computed } from 'vue';
import { Toaster as SonnerToaster } from 'vue-sonner';
import 'vue-sonner/style.css';

const { appearance } = useAppearance();

// sonner exposes a strict 'light' | 'dark' | 'system' theme — our app uses
// the same trio, so pass through directly.
const theme = computed<'light' | 'dark' | 'system'>(() => appearance.value ?? 'system');
</script>

<template>
    <SonnerToaster
        :theme="theme"
        position="bottom-right"
        :close-button="true"
        :rich-colors="true"
        :toast-options="{
            class: 'group text-sm',
            classes: {
                toast: 'group/toast border border-border bg-background text-foreground shadow-md',
                description: 'text-xs text-muted-foreground',
                actionButton: 'bg-primary text-primary-foreground',
                cancelButton: 'bg-muted text-muted-foreground',
            },
        }"
    />
</template>
