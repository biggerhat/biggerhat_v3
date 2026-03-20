<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Collapsible from '@/components/ui/collapsible/Collapsible.vue';
import CollapsibleContent from '@/components/ui/collapsible/CollapsibleContent.vue';
import CollapsibleTrigger from '@/components/ui/collapsible/CollapsibleTrigger.vue';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { isMobileDevice } from '@/composables/useMobileDevice';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
const { toggleSidebar } = useSidebar();

defineProps<{
    items: NavItem[];
}>();

const mobileCheck = () => {
    if (isMobileDevice()) {
        toggleSidebar();
    }
};

const page = usePage<SharedData>();
</script>

<template>
    <div v-for="(item, idx) in items" :key="`navItems-${idx}`">
        <Collapsible v-if="item.collapsible && !item.collapsed" defaultOpen class="group/collapsible">
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
                                    <component :is="item.icon" :className="item.icon_class ?? ''" v-bind="item.icon_props ?? {}" />
                                    <span>{{ item.title }}</span>
                                    <Badge v-if="item.badge" class="ml-auto border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-[9px] font-bold text-amber-600 dark:text-amber-400">{{ item.badge }}</Badge>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
        <Collapsible v-else-if="item.collapsible && item.collapsed" class="group/collapsible">
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
                                    <component :is="item.icon" :className="item.icon_class ?? ''" v-bind="item.icon_props ?? {}" />
                                    <span>{{ item.title }}</span>
                                    <Badge v-if="item.badge" class="ml-auto border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-[9px] font-bold text-amber-600 dark:text-amber-400">{{ item.badge }}</Badge>
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
                            <component :is="item.icon" :className="item.icon_class ?? ''" v-bind="item.icon_props ?? {}" />
                            <span>{{ item.title }}</span>
                            <Badge v-if="item.badge" class="ml-auto border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-[9px] font-bold text-amber-600 dark:text-amber-400">{{ item.badge }}</Badge>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </div>
</template>
