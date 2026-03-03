<script setup lang="ts">
import CharacterCardView from '@/components/CharacterCardView.vue';
import { Button } from '@/components/ui/button';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList, CommandSeparator } from '@/components/ui/command';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import { BookOpen, FileDown, RefreshCw, Search } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps({
    factions: {
        type: Object,
        required: true,
        default() {
            return {};
        },
    },
    featured_character: {
        type: Object,
        required: false,
        default: null,
    },
    stats: {
        type: Object,
        required: true,
        default() {
            return {};
        },
    },
});

const open = ref(false);
const commandSearch = ref({});

const commandRoute = (cmdRoute: string) => {
    router.get(cmdRoute);
    open.value = false;
};

function toggleDialog() {
    if (!commandSearch.value.length) {
        axios.get(route('command')).then(function (response) {
            commandSearch.value = response.data;
        });
    }
    open.value = true;
}
</script>

<template>
    <Head title="Home" />
    <div class="container mx-auto flex flex-col gap-8 p-4">
        <div class="animate-fade-in-up flex flex-col items-center pb-4 pt-8">
            <img src="/images/hat_side.png" class="h-48 md:h-64" alt="BiggerHat.net" />
            <p class="mt-4 text-lg text-muted-foreground">Malifaux Character Database & Tools</p>
            <Button variant="outline" class="mt-6 gap-2" @click="toggleDialog">
                <Search class="size-4" />
                Search characters, keywords, factions...
            </Button>
        </div>

        <div class="animate-fade-in-up grid grid-cols-4 gap-3 md:grid-cols-8" style="animation-delay: 100ms">
            <Link
                v-for="(faction, key) in factions"
                :key="key"
                :href="route('factions.view', key)"
                class="flex flex-col items-center gap-2 rounded-lg p-3 transition-all duration-200 hover:scale-105 hover:bg-muted"
            >
                <img :src="faction.logo" :alt="faction.name" class="size-12 md:size-16" />
                <span class="text-center text-xs font-medium">{{ faction.name }}</span>
            </Link>
        </div>

        <div
            v-if="featured_character && featured_character.standard_miniatures?.length"
            class="animate-fade-in-up flex flex-col items-center gap-3"
            style="animation-delay: 200ms"
        >
            <h2 class="text-lg font-semibold">Featured Character</h2>
            <div class="w-48 md:w-56">
                <CharacterCardView :miniature="featured_character.standard_miniatures[0]" :character-slug="featured_character.slug" />
            </div>
            <Button variant="ghost" size="sm" class="gap-1" @click="router.reload()">
                <RefreshCw class="size-3" />
                Shuffle
            </Button>
        </div>

        <div class="animate-fade-in-up grid gap-3 md:grid-cols-2" style="animation-delay: 300ms">
            <Link
                :href="route('keywords.index')"
                class="flex items-center gap-3 rounded-lg border p-4 transition-all duration-200 hover:border-primary hover:shadow-md"
            >
                <BookOpen class="size-6 text-muted-foreground" />
                <div>
                    <p class="font-medium">Keywords</p>
                    <p class="text-sm text-muted-foreground">Browse all keyword groups</p>
                </div>
            </Link>
            <Link
                :href="route('tools.pdf.index')"
                class="flex items-center gap-3 rounded-lg border p-4 transition-all duration-200 hover:border-primary hover:shadow-md"
            >
                <FileDown class="size-6 text-muted-foreground" />
                <div>
                    <p class="font-medium">PDF Generator</p>
                    <p class="text-sm text-muted-foreground">Create printable character cards</p>
                </div>
            </Link>
        </div>

        <div
            class="animate-fade-in-up flex items-center justify-center gap-6 border-t pt-4 text-sm text-muted-foreground"
            style="animation-delay: 400ms"
        >
            <span>{{ stats.characters }} Characters</span>
            <span>{{ stats.keywords }} Keywords</span>
            <span>8 Factions</span>
        </div>
    </div>

    <CommandDialog v-model:open="open">
        <CommandInput placeholder="Search for a topic..." />
        <CommandList>
            <CommandEmpty>No results found.</CommandEmpty>
            <CommandGroup heading="Factions">
                <CommandItem
                    v-for="faction in commandSearch.factions"
                    v-bind:key="faction.name"
                    @select="commandRoute(faction.route)"
                    :value="faction.name"
                >
                    {{ faction.name }}
                </CommandItem>
            </CommandGroup>
            <CommandSeparator />
            <CommandGroup heading="Keywords">
                <CommandItem
                    v-for="keyword in commandSearch.keywords"
                    v-bind:key="keyword.name"
                    @select="commandRoute(keyword.route)"
                    :value="keyword.name"
                >
                    {{ keyword.name }}
                </CommandItem>
            </CommandGroup>
            <CommandSeparator />
            <CommandGroup heading="Characters">
                <CommandItem
                    v-for="character in commandSearch.characters"
                    v-bind:key="character.name"
                    @select="commandRoute(character.route)"
                    :value="character.name"
                >
                    {{ character.name }}
                </CommandItem>
            </CommandGroup>
            <CommandSeparator />
            <CommandGroup heading="Upgrades">
                <CommandItem
                    v-for="upgrade in commandSearch.upgrades"
                    v-bind:key="upgrade.name"
                    @select="commandRoute(upgrade.route)"
                    :value="upgrade.name"
                >
                    {{ upgrade.name }}
                </CommandItem>
            </CommandGroup>
        </CommandList>
    </CommandDialog>
</template>
