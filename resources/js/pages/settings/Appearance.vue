<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import ThemeTabs from '@/components/ThemeTabs.vue';

import SettingsLayout from '@/layouts/settings/Layout.vue';
</script>

<template>
    <Head title="Appearance settings" />

    <SettingsLayout>
        <div class="space-y-10">
            <section class="space-y-3">
                <HeadingSmall title="Appearance" description="Light, dark, or follow your system setting." />
                <AppearanceTabs />
            </section>

            <section class="space-y-3">
                <HeadingSmall title="Accent theme" description="Tint buttons, links, and focus rings with a faction color. Surfaces stay neutral." />
                <ThemeTabs />
            </section>
        </div>
    </SettingsLayout>
</template>
