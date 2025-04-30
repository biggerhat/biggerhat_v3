<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { Search, Dice6 } from 'lucide-vue-next';
import AlertMessage from "@/components/AlertMessage.vue";
import {
    Command,
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command'
import {Input} from "@/components/ui/input";

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

const open = ref(false);
const commandSearch = ref({});

const commandRoute = (route) => {
    router.get(route);
    open.value = false;
};

function toggleDialog() {
    if (!commandSearch.value.length) {
        axios.get(route('command'))
            .then(function (response) {
                console.log(response.data);
                commandSearch.value = response.data;
            });
    }
    open.value = true;
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto">
            <div class="mx-auto"><Search @click="toggleDialog" class="inline-block cursor-pointer" /><Dice6 class="inline-block ml-2 cursor-pointer" @click="router.get(route('characters.random'))"/></div>
        </div>
    </header>

    <div>
        <CommandDialog v-model:open="open">
            <CommandInput placeholder="Search for a topic..." />
            <CommandList>
                <CommandEmpty>No results found.</CommandEmpty>
                <CommandGroup heading="Factions">
                    <CommandItem v-for="faction in commandSearch.factions" v-bind:key="faction.name" @select="commandRoute(faction.route)" value="faction.name">
                        {{ faction.name }}
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
                <CommandGroup heading="Keywords">
                    <CommandItem v-for="keyword in commandSearch.keywords" v-bind:key="keyword.name" @select="commandRoute(keyword.route)" value="keyword.name">
                        {{ keyword.name }}
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
                <CommandGroup heading="Characters">
                    <CommandItem v-for="character in commandSearch.characters" v-bind:key="character.name" @select="commandRoute(character.route)" value="character.name">
                        {{ character.name }}
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
                <CommandGroup heading="Upgrades">
                    <CommandItem v-for="upgrade in commandSearch.upgrades" v-bind:key="upgrade.name" @select="commandRoute(upgrade.route)" value="upgrade.name">
                        {{ upgrade.name }}
                    </CommandItem>
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    </div>
</template>
