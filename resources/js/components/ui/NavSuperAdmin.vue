<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import Collapsible from "@/components/ui/collapsible/Collapsible.vue";
import {ChevronDown} from "lucide-vue-next";
import CollapsibleContent from "@/components/ui/collapsible/CollapsibleContent.vue";
import CollapsibleTrigger from "@/components/ui/collapsible/CollapsibleTrigger.vue";

defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();
</script>

<template>
    <Collapsible defaultOpen class="group/collapsible">
        <SidebarGroup v-for="item in items" class="px-2 py-0">
            <SidebarGroupLabel asChild>
                <CollapsibleTrigger>
                    {{ item.title }}
                    <ChevronDown class="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-180" />
                </CollapsibleTrigger>
            </SidebarGroupLabel>
            <CollapsibleContent>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in item.items" :key="item.title">
                        <SidebarMenuButton as-child :is-active="item.href === page.url">
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </CollapsibleContent>
        </SidebarGroup>
    </Collapsible>
</template>
