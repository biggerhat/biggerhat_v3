<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import Collapsible from "@/components/ui/collapsible/Collapsible.vue";
import CollapsibleTrigger from "@/components/ui/collapsible/CollapsibleTrigger.vue";
import CollapsibleContent from "@/components/ui/collapsible/CollapsibleContent.vue";
import { ChevronDown } from "lucide-vue-next";
import { useSidebar } from "@/components/ui/sidebar";
import {useMobileDetection} from "vue3-mobile-detection";
let { toggleSidebar, open } = useSidebar();
const { isMobile } = useMobileDetection();

defineProps<{
    items: NavItem[];
}>();

const mobileCheck = () => {
    if (isMobile()) {
        toggleSidebar();
    }
};

const page = usePage<SharedData>();
</script>

<template>
    <div v-for="item in items">
        <Collapsible v-if="item.collapsible" defaultOpen class="group/collapsible">
            <SidebarGroup class="px-2 py-0">
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
                                <Link :href="item.href" @click="mobileCheck">
                                    <component :is="item.icon" :className="item.icon_class ?? ''" />
                                    <span>{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
        <SidebarGroup v-else class="px-2 py-0">
            <SidebarGroupLabel>{{ item.title }}</SidebarGroupLabel>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in item.items" :key="item.title">
                    <SidebarMenuButton as-child :is-active="item.href === page.url">
                        <Link :href="item.href" @click="mobileCheck">
                            <component :is="item.icon" :className="item.icon_class ?? ''" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </div>
</template>
