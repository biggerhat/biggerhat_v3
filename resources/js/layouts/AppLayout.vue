<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

const year = ref(new Date().getFullYear());

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex-1">
            <slot />
        </div>
        <footer class="p-toolbar p-component mt-6 !block !rounded-none !border-b-0 !border-l-0 !border-r-0 border-t border-dashed py-3">
            <div class="container mx-auto sm:px-4">
                <div class="my-2 flex flex-wrap items-center gap-x-4 gap-y-1 px-2 text-xs lg:px-0">
                    <a href="https://discord.gg/veGzyuTvGW" target="_blank" class="inline-flex items-center gap-1 hover:underline">
                        Join Our Discord
                    </a>
                    <a href="https://ko-fi.com/biggerhat" target="_blank" class="inline-flex items-center gap-1 hover:underline"> Donate on Ko-fi </a>
                    <Link :href="route('feedback.show')" class="inline-flex items-center gap-1 hover:underline">Feedback</Link>
                    <Link :href="route('privacy')" class="inline-flex items-center gap-1 hover:underline">Privacy</Link>
                </div>
                <div class="my-2 block px-2 text-xs lg:px-0">
                    Portions of the materials used are copyrighted works of
                    <a href="https://www.wyrd-games.net/" target="_wyrd" class="hover:underline" alt="Wyrd Miniatures">Wyrd Miniatures, LLC</a>, in
                    the United States of America and elsewhere. All rights reserved, Wyrd Miniatures, LLC. This material is not official and is not
                    endorsed by Wyrd Miniatures, LLC.
                </div>
                <div class="my-2 block px-2 text-xs lg:px-0">
                    All other content &copy; {{ year }} BiggerHat. Designed and Built by Mark C and Brandan B.
                </div>
            </div>
        </footer>
    </AppLayout>
</template>
