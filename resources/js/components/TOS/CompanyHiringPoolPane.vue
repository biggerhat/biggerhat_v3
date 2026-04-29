<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { CircleX, Crown, Search, UserPlus } from 'lucide-vue-next';

interface SpecialRule {
    id: number;
    slug: string;
    name: string;
}

interface UnitMin {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    scrip: number;
    restriction: string | null;
    combined_arms_child_id: number | null;
    special_unit_rules: SpecialRule[];
    hire_category?: 'direct' | 'neutral';
}

type PoolFilter = 'all' | 'direct' | 'neutral' | 'commander';
type PoolSort = 'name' | 'scrip';

const props = defineProps<{
    pool: UnitMin[];
    counts: { all: number; direct: number; neutral: number; commander: number };
    filterText: string;
    poolFilter: PoolFilter;
    poolSort: PoolSort;
    hasCommander: boolean;
    scripRemaining: number;
}>();

const emit = defineEmits<{
    (e: 'update:filterText', v: string): void;
    (e: 'update:poolFilter', v: PoolFilter): void;
    (e: 'update:poolSort', v: PoolSort): void;
    (e: 'hire', u: UnitMin, asCommander: boolean): void;
}>();

const filters: Array<{ key: PoolFilter; label: string }> = [
    { key: 'all', label: 'All' },
    { key: 'direct', label: 'Direct' },
    { key: 'neutral', label: 'Neutral' },
    { key: 'commander', label: 'Cmdr' },
];
const sorts: Array<{ key: PoolSort; label: string }> = [
    { key: 'name', label: 'Name' },
    { key: 'scrip', label: 'Scrip' },
];

function isCommanderEligible(u: UnitMin): boolean {
    return u.special_unit_rules.some((r) => r.slug === 'commander');
}

function unaffordable(u: UnitMin): boolean {
    return u.scrip > props.scripRemaining;
}
</script>

<template>
    <Card class="overflow-hidden lg:sticky lg:top-2">
        <div class="flex items-center justify-between border-b px-3 py-2 sm:px-4">
            <h2 class="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Hiring Pool</h2>
            <Badge variant="secondary" class="px-1.5 py-0 text-[10px]">{{ pool.length }} shown</Badge>
        </div>
        <CardContent class="space-y-2 p-2 sm:p-3">
            <!-- Search -->
            <div class="relative">
                <Search class="absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    :model-value="filterText"
                    placeholder="Search by name…"
                    class="pl-9 pr-8"
                    @update:model-value="emit('update:filterText', String($event))"
                />
                <button
                    v-if="filterText"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                    @click="emit('update:filterText', '')"
                >
                    <CircleX class="size-4" />
                </button>
            </div>

            <!-- Filter chips -->
            <div class="flex flex-wrap items-center gap-1">
                <Button
                    v-for="f in filters"
                    :key="f.key"
                    :variant="poolFilter === f.key ? 'default' : 'outline'"
                    size="sm"
                    class="h-6 gap-1 px-2 text-[11px]"
                    @click="emit('update:poolFilter', f.key)"
                >
                    {{ f.label }}
                    <span class="text-[10px] opacity-60">{{ counts[f.key] }}</span>
                </Button>
            </div>

            <!-- Sort -->
            <div class="flex items-center gap-1">
                <span class="text-[11px] text-muted-foreground">Sort:</span>
                <Button
                    v-for="s in sorts"
                    :key="s.key"
                    :variant="poolSort === s.key ? 'default' : 'ghost'"
                    size="sm"
                    class="h-5 px-1.5 text-[10px]"
                    @click="emit('update:poolSort', s.key)"
                >{{ s.label }}</Button>
            </div>

            <!-- Pool list -->
            <div class="-mx-1 max-h-[60vh] overflow-y-auto px-1">
                <div v-if="!pool.length" class="py-6 text-center text-xs text-muted-foreground">
                    No matching units.
                </div>
                <div v-else class="space-y-1">
                    <div
                        v-for="u in pool"
                        :key="u.id"
                        class="flex items-center gap-2 rounded-md border bg-card px-2 py-1.5 text-xs transition-colors hover:border-primary/40 hover:bg-accent/40"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5">
                                <Crown
                                    v-if="isCommanderEligible(u)"
                                    class="size-3 shrink-0 text-amber-500"
                                    aria-label="Commander-eligible"
                                />
                                <span class="truncate font-medium">{{ u.name }}</span>
                                <Badge
                                    v-if="u.hire_category === 'neutral'"
                                    variant="outline"
                                    class="px-1 py-0 text-[9px]"
                                >Neutral</Badge>
                            </div>
                            <div v-if="u.title" class="truncate text-[10px] italic text-muted-foreground">{{ u.title }}</div>
                        </div>
                        <span class="shrink-0 text-[11px] tabular-nums text-muted-foreground">{{ u.scrip }}s</span>

                        <div class="flex shrink-0 items-center gap-0.5">
                            <Button
                                v-if="!hasCommander && isCommanderEligible(u)"
                                variant="ghost"
                                size="icon"
                                class="size-7 text-amber-600 hover:bg-amber-500/10 hover:text-amber-700 dark:text-amber-400"
                                title="Add as Commander"
                                @click="emit('hire', u, true)"
                            >
                                <Crown class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7 text-muted-foreground hover:bg-primary/10 hover:text-primary"
                                :disabled="hasCommander && unaffordable(u)"
                                :title="hasCommander && unaffordable(u) ? 'Over the Scrip budget' : 'Hire unit'"
                                @click="emit('hire', u, false)"
                            >
                                <UserPlus class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
