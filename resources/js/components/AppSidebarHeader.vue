<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { Dice6, Loader2, Search } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItemType[];
}>();

interface CommandEntry {
    name: string;
    route: string;
}
interface CommandSearchResults {
    factions?: CommandEntry[];
    keywords?: CommandEntry[];
    characters?: CommandEntry[];
    upgrades?: CommandEntry[];
    miniatures?: CommandEntry[];
    packages?: CommandEntry[];
}

const open = ref(false);
const commandSearch = ref<CommandSearchResults | null>(null);
const loading = ref(false);
const loadError = ref<string | null>(null);

const commandRoute = (route: string) => {
    router.get(route);
    open.value = false;
};

async function toggleDialog() {
    open.value = true;
    // Only fetch the catalog once per session. A null sentinel distinguishes
    // "never fetched" from "fetched but empty"; without it the old `.length`
    // check misfired against `{}` and refetched on every open.
    if (commandSearch.value !== null) return;
    loading.value = true;
    loadError.value = null;
    try {
        const response = await axios.get(route('command'));
        commandSearch.value = response.data ?? {};
    } catch {
        loadError.value = 'Could not load search. Please try again.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-2 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 sm:px-6 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="ml-auto">
            <div class="mx-auto">
                <button aria-label="Search" @click="toggleDialog"><Search class="inline-block" /></button
                ><button aria-label="Random character" class="ml-2" @click="router.get(route('characters.random'))">
                    <Dice6 class="inline-block" />
                </button>
            </div>
        </div>
    </header>

    <div>
        <CommandDialog v-model:open="open">
            <CommandInput placeholder="Search for a topic..." />
            <CommandList>
                <div v-if="loading" class="flex items-center justify-center py-6 text-xs text-muted-foreground">
                    <Loader2 class="mr-2 size-4 animate-spin" /> Loading…
                </div>
                <div v-else-if="loadError" class="px-4 py-6 text-center text-xs text-destructive">
                    {{ loadError }}
                    <button class="ml-2 underline" @click="toggleDialog">Retry</button>
                </div>
                <CommandEmpty>No results found.</CommandEmpty>
                <CommandGroup heading="Factions">
                    <CommandItem
                        v-for="faction in commandSearch?.factions ?? []"
                        v-bind:key="faction.name"
                        @select="commandRoute(faction.route)"
                        value="faction.name"
                    >
                        {{ faction.name }}
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
                <CommandGroup heading="Keywords">
                    <CommandItem
                        v-for="keyword in commandSearch?.keywords ?? []"
                        v-bind:key="keyword.name"
                        @select="commandRoute(keyword.route)"
                        value="keyword.name"
                    >
                        {{ keyword.name }}
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
                <CommandGroup heading="Characters">
                    <CommandItem
                        v-for="character in commandSearch?.characters ?? []"
                        v-bind:key="character.name"
                        @select="commandRoute(character.route)"
                        value="character.name"
                    >
                        {{ character.name }}
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
                <CommandGroup heading="Upgrades">
                    <CommandItem
                        v-for="upgrade in commandSearch?.upgrades ?? []"
                        v-bind:key="upgrade.name"
                        @select="commandRoute(upgrade.route)"
                        value="upgrade.name"
                    >
                        {{ upgrade.name }}
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
                <CommandGroup heading="Miniatures">
                    <CommandItem
                        v-for="mini in commandSearch?.miniatures ?? []"
                        v-bind:key="mini.name"
                        @select="commandRoute(mini.route)"
                        :value="mini.name"
                    >
                        {{ mini.name }}
                    </CommandItem>
                </CommandGroup>
                <CommandSeparator />
                <CommandGroup heading="Packages">
                    <CommandItem v-for="pkg in commandSearch?.packages ?? []" v-bind:key="pkg.name" @select="commandRoute(pkg.route)" :value="pkg.name">
                        {{ pkg.name }}
                    </CommandItem>
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    </div>
</template>
