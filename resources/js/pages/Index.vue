<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Search } from 'lucide-vue-next';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import Breadcrumb from "@/components/ui/breadcrumb/Breadcrumb.vue";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];
</script>

<template>
        <div class="container flex h-full flex-1 flex-col gap-4 rounded-xl p-4 mx-auto">
            <div class="m-auto">
                <img src='/images/hat_side.png' class="h-72" alt="BiggerHat.net" />
                <div class="relative w-full items-center mx-auto">
                    <Input id="search" type="text" placeholder="Search..." class="pl-10 mt-16 `" />
                    <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
                        <Search class="size-6 text-muted-foreground" />
                    </span>
                </div>
            </div>
        </div>
</template>
