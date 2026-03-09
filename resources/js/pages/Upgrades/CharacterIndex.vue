<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import UpgradeCardView from '@/components/UpgradeCardView.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useStaggeredEntry } from '@/composables/useStaggeredEntry';
import { Head, Link } from '@inertiajs/vue3';
import { LayoutGrid, List } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface UpgradeMaster {
    display_name: string;
    slug: string;
}

interface SelectOption {
    value: string;
    name: string;
}

interface CharacterUpgrade {
    id: number;
    name: string;
    slug: string;
    type: string | null;
    type_label: string | null;
    faction: string | null;
    faction_label: string | null;
    faction_color: string | null;
    faction_logo: string | null;
    limitations: string | null;
    limitations_label: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
    characters_count: number;
    masters: UpgradeMaster[];
}

interface FactionInfo {
    slug: string;
    name: string;
    color: string;
    logo: string;
}

const props = defineProps<{
    upgrades: CharacterUpgrade[];
    factions: Record<string, FactionInfo>;
    types: SelectOption[];
}>();

const searchQuery = ref('');
const selectedFaction = ref('all');
const selectedType = ref('all');

const filteredUpgrades = computed(() => {
    let result = props.upgrades;

    if (selectedFaction.value !== 'all') {
        result = result.filter((u) => u.faction === selectedFaction.value);
    }

    if (selectedType.value !== 'all') {
        result = result.filter((u) => u.type === selectedType.value);
    }

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter((u) => u.name.toLowerCase().includes(query));
    }

    return result;
});

const filteredCount = computed(() => filteredUpgrades.value.length);
const totalCount = computed(() => props.upgrades.length);
const isFiltered = computed(() => filteredCount.value !== totalCount.value);

const { delays } = useStaggeredEntry(filteredCount);
</script>

<template>
    <Head title="Character Upgrades" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Character Upgrades">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">{{ totalCount }} Upgrades</div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <div class="flex flex-col gap-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                <Input v-model="searchQuery" class="max-w-sm" placeholder="Filter by name..." />
                <div class="flex flex-wrap items-center gap-3">
                    <Select v-model="selectedType">
                        <SelectTrigger class="w-48">
                            <SelectValue placeholder="All Types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Types</SelectItem>
                            <SelectItem v-for="type in types" :key="type.value" :value="type.value">
                                {{ type.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="selectedFaction">
                        <SelectTrigger class="w-48">
                            <SelectValue placeholder="All Factions" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All Factions</SelectItem>
                            <SelectItem v-for="(faction, key) in factions" :key="key" :value="faction.slug">
                                <span class="flex items-center gap-2">
                                    <img :src="faction.logo" class="h-4 w-4" :alt="faction.name" />
                                    {{ faction.name }}
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <div v-if="isFiltered" class="whitespace-nowrap text-sm text-muted-foreground">{{ filteredCount }} of {{ totalCount }}</div>
                </div>
            </div>

            <Tabs default-value="cards">
                <div class="mb-4 flex items-center justify-between">
                    <TabsList class="gap-1">
                        <TabsTrigger value="cards">
                            <LayoutGrid class="size-4" />
                            Cards
                        </TabsTrigger>
                        <TabsTrigger value="table">
                            <List class="size-4" />
                            Table
                        </TabsTrigger>
                    </TabsList>
                </div>

                <TabsContent value="cards">
                    <div v-if="filteredUpgrades.length" class="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-4">
                        <div
                            v-for="(upgrade, index) in filteredUpgrades"
                            :key="upgrade.id"
                            class="animate-fade-in-up opacity-0"
                            :style="delays[index]"
                        >
                            <UpgradeCardView :upgrade="upgrade" />
                        </div>
                    </div>
                    <EmptyState v-else title="No character upgrades found" description="Try adjusting your search or filters." />
                </TabsContent>

                <TabsContent value="table">
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Faction</TableHead>
                                    <TableHead>Masters</TableHead>
                                    <TableHead class="text-right">Characters</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="filteredUpgrades.length">
                                    <TableRow v-for="upgrade in filteredUpgrades" :key="upgrade.id">
                                        <TableCell>
                                            <Link :href="route('upgrades.view', { upgrade: upgrade.slug })" class="font-medium hover:underline">
                                                {{ upgrade.name }}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Badge v-if="upgrade.type_label" variant="secondary" class="text-xs">
                                                {{ upgrade.type_label }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <span v-if="upgrade.faction_label" class="flex items-center gap-1.5">
                                                <img
                                                    v-if="upgrade.faction_logo"
                                                    :src="upgrade.faction_logo"
                                                    class="h-4 w-4"
                                                    :alt="upgrade.faction_label"
                                                />
                                                {{ upgrade.faction_label }}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div class="flex flex-wrap gap-1">
                                                <Badge v-for="master in upgrade.masters" :key="master.slug" variant="outline" class="text-xs">
                                                    {{ master.display_name }}
                                                </Badge>
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-right">{{ upgrade.characters_count }}</TableCell>
                                    </TableRow>
                                </template>
                                <template v-else>
                                    <TableRow>
                                        <TableCell :colspan="5">
                                            <EmptyState />
                                        </TableCell>
                                    </TableRow>
                                </template>
                            </TableBody>
                        </Table>
                    </div>
                </TabsContent>
            </Tabs>
        </div>
    </div>
</template>
