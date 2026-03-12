<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

const navItems: NavItem[] = [
    {
        title: 'Profile',
        href: '/settings/profile',
    },
    {
        title: 'Password',
        href: '/settings/password',
    },
    {
        title: 'Appearance',
        href: '/settings/appearance',
    },
];

const page = usePage();

const currentPath = page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';
</script>

<template>
    <div class="px-4 py-6">
        <Heading title="Settings" description="Manage your profile and account settings" />

        <nav class="mb-6 flex gap-1 border-b">
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    currentPath === item.href
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:border-border hover:text-foreground'
                "
            >
                {{ item.title }}
            </Link>
        </nav>

        <div class="max-w-2xl">
            <section class="max-w-xl space-y-12">
                <slot />
            </section>
        </div>
    </div>
</template>
