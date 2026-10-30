<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Collapsible from '@/components/ui/collapsible/Collapsible.vue';
import CollapsibleContent from '@/components/ui/collapsible/CollapsibleContent.vue';
import CollapsibleTrigger from '@/components/ui/collapsible/CollapsibleTrigger.vue';
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { isMobileDevice } from '@/composables/useMobileDevice';
import { findActiveItem } from '@/lib/navActive';
import { type NavGroup, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, onMounted, reactive } from 'vue';

const props = defineProps<{
    items: NavGroup[];
    /** When set, collapsible groups remember their open/closed state in localStorage under this key. */
    storageKey?: string;
}>();

const { toggleSidebar } = useSidebar();
const page = usePage<SharedData>();

const mobileCheck = () => {
    if (isMobileDevice()) {
        toggleSidebar();
    }
};

const activeItem = computed(() => findActiveItem(props.items, page.url));

// Per-group overrides of the group's default (`collapsed`) state. Filled from
// localStorage after mount (so SSR and first client render agree) and by the
// user toggling a group.
const openState = reactive<Record<string, boolean>>({});

const storageName = computed(() => (props.storageKey ? `sidebar-groups:${props.storageKey}` : null));

onMounted(() => {
    if (!storageName.value) return;
    try {
        const saved = JSON.parse(localStorage.getItem(storageName.value) ?? '{}');
        if (saved && typeof saved === 'object') Object.assign(openState, saved);
    } catch {
        // Storage unavailable or corrupt — fall back to defaults.
    }
});

function isOpen(group: NavGroup): boolean {
    const key = group.title ?? '';
    // The group holding the current page always starts open, so the active link is never hidden.
    if (activeItem.value && group.items.includes(activeItem.value) && openState[key] === undefined) return true;
    return openState[key] ?? !group.collapsed;
}

function setOpen(group: NavGroup, open: boolean) {
    openState[group.title ?? ''] = open;
    if (!storageName.value) return;
    try {
        localStorage.setItem(storageName.value, JSON.stringify(openState));
    } catch {
        // Ignore — persistence is a convenience only.
    }
}

const isActive = (item: NavItem) => item === activeItem.value;
</script>

<template>
    <div v-for="(group, idx) in items" :key="`navItems-${idx}`">
        <component
            :is="group.collapsible ? Collapsible : 'div'"
            v-bind="
                group.collapsible ? { open: isOpen(group), 'onUpdate:open': (open: boolean) => setOpen(group, open), class: 'group/collapsible' } : {}
            "
        >
            <SidebarGroup class="px-2 py-0">
                <SidebarGroupLabel v-if="group.collapsible" asChild>
                    <CollapsibleTrigger>
                        {{ group.title }}
                        <ChevronDown class="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-180" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <SidebarGroupLabel v-else>{{ group.title }}</SidebarGroupLabel>

                <component :is="group.collapsible ? CollapsibleContent : 'div'">
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in group.items" :key="item.title">
                            <SidebarMenuButton as-child :is-active="isActive(item)">
                                <a v-if="item.external" :href="item.href" @click="mobileCheck">
                                    <component :is="item.icon" :className="item.icon_class ?? ''" v-bind="item.icon_props ?? {}" />
                                    <span>{{ item.title }}</span>
                                    <Badge
                                        v-if="item.badge"
                                        class="ml-auto border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-2xs font-bold text-amber-800 dark:text-amber-400"
                                        >{{ item.badge }}</Badge
                                    >
                                </a>
                                <Link v-else :href="item.href" @click="mobileCheck">
                                    <component :is="item.icon" :className="item.icon_class ?? ''" v-bind="item.icon_props ?? {}" />
                                    <span>{{ item.title }}</span>
                                    <Badge
                                        v-if="item.badge"
                                        class="ml-auto border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-2xs font-bold text-amber-800 dark:text-amber-400"
                                        >{{ item.badge }}</Badge
                                    >
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </component>
            </SidebarGroup>
        </component>
    </div>
</template>
