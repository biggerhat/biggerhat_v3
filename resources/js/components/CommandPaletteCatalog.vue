<script setup lang="ts">
/**
 * Renders the dynamic catalog groups (factions, keywords, characters, etc.)
 * for the global command palette — but only once the user has typed a search
 * query. Without this gate, the palette has to mount every item in the
 * catalog (500+ miniatures, hundreds of characters) on open, which makes
 * first-open feel sluggish and Reka's listbox keyboard-nav index churn over
 * thousands of nodes.
 *
 * This component *must* be rendered inside a `<Command>` / `<CommandDialog>`
 * so that `useCommand()` can read the search state cmdk maintains from
 * `<CommandInput>`'s model.
 */
import { CommandGroup, CommandItem, CommandSeparator, useCommand } from '@/components/ui/command';
import { Layers, Package, Shield, Sparkles, Tags, Users } from 'lucide-vue-next';
import { computed } from 'vue';

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

defineProps<{
    catalog: CommandSearchResults | null;
}>();

const emit = defineEmits<{
    (e: 'select', route: string): void;
}>();

const { filterState } = useCommand();

// Gate dynamic groups behind a non-empty search so the initial open is fast.
// Trimmed + lowercase because cmdk's filterState.search holds the raw value.
const hasQuery = computed(() => (filterState.search ?? '').trim().length > 0);
</script>

<template>
    <template v-if="hasQuery">
        <CommandSeparator />

        <CommandGroup v-if="catalog?.factions?.length" heading="Factions">
            <CommandItem
                v-for="faction in catalog.factions"
                :key="`faction-${faction.route}`"
                :value="`faction:${faction.name}`"
                @select="emit('select', faction.route)"
            >
                <Shield class="mr-2 size-4 text-muted-foreground" />
                {{ faction.name }}
            </CommandItem>
        </CommandGroup>

        <CommandGroup v-if="catalog?.keywords?.length" heading="Keywords">
            <CommandItem
                v-for="keyword in catalog.keywords"
                :key="`keyword-${keyword.route}`"
                :value="`keyword:${keyword.name}`"
                @select="emit('select', keyword.route)"
            >
                <Tags class="mr-2 size-4 text-muted-foreground" />
                {{ keyword.name }}
            </CommandItem>
        </CommandGroup>

        <CommandGroup v-if="catalog?.characters?.length" heading="Characters">
            <CommandItem
                v-for="character in catalog.characters"
                :key="`character-${character.route}`"
                :value="`character:${character.name}`"
                @select="emit('select', character.route)"
            >
                <Users class="mr-2 size-4 text-muted-foreground" />
                {{ character.name }}
            </CommandItem>
        </CommandGroup>

        <CommandGroup v-if="catalog?.upgrades?.length" heading="Upgrades">
            <CommandItem
                v-for="upgrade in catalog.upgrades"
                :key="`upgrade-${upgrade.route}`"
                :value="`upgrade:${upgrade.name}`"
                @select="emit('select', upgrade.route)"
            >
                <Sparkles class="mr-2 size-4 text-muted-foreground" />
                {{ upgrade.name }}
            </CommandItem>
        </CommandGroup>

        <CommandGroup v-if="catalog?.miniatures?.length" heading="Miniatures">
            <CommandItem
                v-for="mini in catalog.miniatures"
                :key="`mini-${mini.route}`"
                :value="`miniature:${mini.name}`"
                @select="emit('select', mini.route)"
            >
                <Layers class="mr-2 size-4 text-muted-foreground" />
                {{ mini.name }}
            </CommandItem>
        </CommandGroup>

        <CommandGroup v-if="catalog?.packages?.length" heading="Packages">
            <CommandItem
                v-for="pkg in catalog.packages"
                :key="`pkg-${pkg.route}`"
                :value="`package:${pkg.name}`"
                @select="emit('select', pkg.route)"
            >
                <Package class="mr-2 size-4 text-muted-foreground" />
                {{ pkg.name }}
            </CommandItem>
        </CommandGroup>
    </template>
    <!-- When no query, show a quiet hint so the palette doesn't look empty
         beneath the Quick Actions. -->
    <div v-else class="px-4 py-3 text-center text-[11px] text-muted-foreground">
        Type to search the database — characters, keywords, factions, upgrades, miniatures, packages.
    </div>
</template>
