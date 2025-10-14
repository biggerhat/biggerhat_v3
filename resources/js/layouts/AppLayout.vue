<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';
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
        <slot />
        <footer class="py-3 p-toolbar p-component border-dashed border-t !rounded-none !border-l-0 !border-r-0 !border-b-0 !block mt-6">
            <!--        <div class="container grid grid-cols-1 gap-3 mx-auto mt-3 lg:grid-cols-10 auto-cols-auto">-->
            <!--            <div class="w-full mx-auto">-->
            <!--                <div-->
            <!--                    class="w-32 mx-auto font-semibold text-center border-b lg:text-left text-md lg:w-full">-->
            <!--                    Characters</div>-->
            <!--                <ul class="font-sans text-center list-none lg:text-left">-->
            <!--                    <li class="text-sm">Advanced Search</li>-->
            <!--                    <li class="text-sm">Random Character</li>-->
            <!--                    <li class="text-sm">Masters</li>-->
            <!--                    <li class="text-sm">Upgrades</li>-->
            <!--                </ul>-->
            <!--            </div>-->
            <!--            <div class="w-full mx-auto">-->
            <!--                <div-->
            <!--                    class="w-32 mx-auto font-semibold text-center border-b lg:text-left text-md lg:w-full">-->
            <!--                    Characters</div>-->
            <!--                <ul class="font-sans text-center list-none lg:text-left">-->
            <!--                    <li class="text-sm">Advanced Search</li>-->
            <!--                    <li class="text-sm">Random Character</li>-->
            <!--                    <li class="text-sm">Masters</li>-->
            <!--                    <li class="text-sm">Upgrades</li>-->
            <!--                </ul>-->
            <!--            </div>-->
            <!--            <div class="w-full mx-auto">-->
            <!--                <div-->
            <!--                    class="w-32 mx-auto font-semibold text-center border-b lg:text-left text-md lg:w-full">-->
            <!--                    Characters</div>-->
            <!--                <ul class="font-sans text-center list-none lg:text-left">-->
            <!--                    <li class="text-sm">Advanced Search</li>-->
            <!--                    <li class="text-sm">Random Character</li>-->
            <!--                    <li class="text-sm">Masters</li>-->
            <!--                    <li class="text-sm">Upgrades</li>-->
            <!--                </ul>-->
            <!--            </div>-->
            <!--            <div class="w-full mx-auto">-->
            <!--                <div-->
            <!--                    class="w-32 mx-auto font-semibold text-center border-b lg:text-left text-md lg:w-auto">-->
            <!--                    Account</div>-->
            <!--                <ul class="font-sans text-center list-none lg:text-left">-->
            <!--                    <li class="text-sm"><a href="/login" class="hover:underline">Login</a></li>-->
            <!--                    <li class="text-sm"><a href="/register" class="hover:underline">Register</a></li>-->
            <!--                    <li class="text-sm"><a href="/user/profile" class="hover:underline">Profile</a></li>-->
            <!--                    <li class="text-sm"><a href="/user/logout" class="hover:underline">Logout</a></li>-->
            <!--                </ul>-->
            <!--            </div>-->
            <!--            <div class="w-full mx-auto">-->
            <!--                <div-->
            <!--                    class="mx-auto font-semibold text-center border-b lg:text-left text-md w-28 lg:w-auto">-->
            <!--                    Contact Us</div>-->
            <!--                <ul class="font-sans text-center list-none lg:text-left">-->
            <!--                    <li class="text-sm"><a href="{{ route('about') }}" class="hover:underline">About</a>-->
            <!--                    </li>-->
            <!--                    <li class="text-sm"><a href="https://ko-fi.com/biggerhat" target="_kofi"-->
            <!--                                           class="hover:underline">Donate with Ko-fi</a></li>-->
            <!--                    <li class="text-sm"><a href="https://discord.gg/25cNcmZd9H" class="hover:underline"-->
            <!--                                           target="discord">Discord Server</a></li>-->
            <!--                    <li class="text-sm"><a href="https://www.youtube.com/channel/UC095wgLOM_nojBIdzVUtfWg"-->
            <!--                                           class="hover:underline" target="youtube">YouTube</a></li>-->
            <!--                    <li class="text-sm"><a href="https://www.facebook.com/biggerhat/"-->
            <!--                                           class="hover:underline" target="facebook">Facebook</a></li>-->
            <!--                    <li class="text-sm"><a href="https://www.reddit.com/user/Biggerhat"-->
            <!--                                           class="hover:underline" target="reddit">Reddit</a></li>-->
            <!--                </ul>-->
            <!--            </div>-->
            <!--        </div>-->
            <div class="container mx-auto px-4">
                <div class="block px-2 my-2 text-xs lg:px-0">
                    Portions of the materials used are copyrighted works of <a href="https://www.wyrd-games.net/" target="_wyrd" class="hover:underline" alt="Wyrd Miniatures">Wyrd Miniatures, LLC</a>, in the United
                    States of America and elsewhere. All rights reserved, Wyrd Miniatures, LLC. This material is not official and is not endorsed by Wyrd Miniatures, LLC.
                </div>
                <div class="block px-2 my-2 text-xs lg:px-0">
                    All other content &copy; {{ year }} BiggerHat. Designed and Built by Mark C and Brandan B.
                </div>
            </div>
        </footer>
    </AppLayout>

</template>
