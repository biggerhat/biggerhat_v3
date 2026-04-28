<script setup lang="ts">
import ClearableSelect from '@/components/ClearableSelect.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import type { TosSelectOption } from '@/types/tos';
import { ChevronDown } from 'lucide-vue-next';

/**
 * Renders every filter section for the TOS Advanced Search page. Used twice
 * — once for the desktop sidebar and once inside the mobile FilterPanel —
 * so the markup lives in a single component and stays in sync between the
 * two surfaces.
 *
 * Mirrors the section split from `pages/Search/View.vue` (the Malifaux
 * Advanced Search): identity, stats, actions, abilities, triggers, has,
 * sorting. Only one section is open at a time, like Faction/View.
 */
// `params` is the form-state object owned by the parent. Using defineModel
// gives the child a writable ref that auto-emits update:params on mutation,
// so v-model="params.name" works in the parent without tripping Vue's
// "no-mutating-props" rule. `sectionsOpen` follows the same pattern.
import { computed } from 'vue';

const params = defineModel<Record<string, string | null>>('params', { required: true });
const sectionsOpen = defineModel<Record<string, boolean>>('sectionsOpen', { required: true });

/**
 * SearchableMultiselect speaks string[]; our params shape persists the same
 * filter as a comma-separated string so it round-trips through the URL as a
 * single query parameter. These computed wrappers translate between the two.
 * Generated factories so each multi-select gets its own writable ref without
 * stomping on `params` shape.
 */
function multiArray(key: string) {
    return computed<string[]>({
        get: () => (params.value[key] ?? '').split(',').filter(Boolean),
        set: (next) => {
            params.value[key] = next.length ? next.join(',') : null;
        },
    });
}

const allegianceArray = multiArray('allegiance');
const specialRuleArray = multiArray('special_rule');
const actionTypeArray = multiArray('action_type');

defineProps<{
    allegiances: TosSelectOption[];
    specialRules: TosSelectOption[];
    restrictionOptions: TosSelectOption[];
    actionTypes: TosSelectOption[];
    usageLimits: TosSelectOption[];
    sortOptions: TosSelectOption[];
    sortTypes: TosSelectOption[];
    sideOptions: TosSelectOption[];
    logicOptions: TosSelectOption[];
    booleanOptions: TosSelectOption[];
    hasFlags: readonly string[];
    activeHasFlags: readonly string[];
}>();

const emit = defineEmits<{
    toggle: [section: string];
    toggleHas: [flag: string];
}>();
</script>

<template>
    <!-- Identity / Filters -->
    <Collapsible :open="sectionsOpen.identity" @update:open="emit('toggle', 'identity')">
        <CollapsibleTrigger
            class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
        >
            Filters
            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.identity }" />
        </CollapsibleTrigger>
        <CollapsibleContent class="space-y-3 px-1 pt-3">
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Name</label>
                <Input v-model="params.name" placeholder="Unit / asset / stratagem name" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Description (any rules text)</label>
                <Input v-model="params.description" placeholder="e.g. push, repulsion, terrifying" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Allegiance</label>
                <SearchableMultiselect
                    v-model="allegianceArray"
                    placeholder="Any allegiance"
                    :options="allegiances"
                    option-label="name"
                    option-value="value"
                />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Logic</label>
                    <ClearableSelect v-model="params.allegiance_logic" placeholder="AND" :options="logicOptions" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Restriction</label>
                    <ClearableSelect v-model="params.restriction" placeholder="Any" :options="restrictionOptions" />
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Special Rule</label>
                <SearchableMultiselect
                    v-model="specialRuleArray"
                    placeholder="Any rule"
                    :options="specialRules"
                    option-label="name"
                    option-value="value"
                />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Logic</label>
                    <ClearableSelect v-model="params.special_rule_logic" placeholder="AND" :options="logicOptions" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Tactics (Standard)</label>
                    <Input v-model="params.tactics" placeholder="e.g. 2" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Tactics (Glory)</label>
                    <Input v-model="params.glory_tactics" placeholder="e.g. 3" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Scrip min</label>
                    <Input v-model="params.scrip_min" type="number" placeholder="–99" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Scrip max</label>
                    <Input v-model="params.scrip_max" type="number" placeholder="99" />
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>

    <!-- Stats (per-side) -->
    <Collapsible :open="sectionsOpen.stats" @update:open="emit('toggle', 'stats')">
        <CollapsibleTrigger
            class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
        >
            Stats
            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.stats }" />
        </CollapsibleTrigger>
        <CollapsibleContent class="space-y-3 px-1 pt-3">
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Side</label>
                <ClearableSelect v-model="params.side" placeholder="Both" :options="sideOptions" />
            </div>
            <div v-for="stat in (['speed', 'defense', 'willpower', 'armor'] as const)" :key="stat" class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <label class="text-xs font-medium capitalize text-muted-foreground">{{ stat }} min</label>
                    <Input v-model="params[`${stat}_min`]" type="number" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium capitalize text-muted-foreground">{{ stat }} max</label>
                    <Input v-model="params[`${stat}_max`]" type="number" />
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>

    <!-- Actions -->
    <Collapsible :open="sectionsOpen.actions" @update:open="emit('toggle', 'actions')">
        <CollapsibleTrigger
            class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
        >
            Actions
            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.actions }" />
        </CollapsibleTrigger>
        <CollapsibleContent class="space-y-3 px-1 pt-3">
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Action Name</label>
                <Input v-model="params.action_name" placeholder="e.g. Mighty Jaws" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Action Type</label>
                <SearchableMultiselect
                    v-model="actionTypeArray"
                    placeholder="Any type"
                    :options="actionTypes"
                    option-label="name"
                    option-value="value"
                />
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">AV min</label>
                    <Input v-model="params.action_av_min" type="number" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">AV max</label>
                    <Input v-model="params.action_av_max" type="number" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Strength min</label>
                    <Input v-model="params.action_strength_min" type="number" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Strength max</label>
                    <Input v-model="params.action_strength_max" type="number" />
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Range</label>
                <Input v-model="params.action_range" placeholder="e.g. 8 or melee" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Usage Limit</label>
                <ClearableSelect v-model="params.action_usage_limit" placeholder="Any" :options="usageLimits" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Description</label>
                <Input v-model="params.action_description" placeholder="Match action body text" />
            </div>
            <div class="grid grid-cols-3 gap-2">
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Piercing</label>
                    <ClearableSelect v-model="params.action_is_piercing" placeholder="Any" :options="booleanOptions" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Accurate</label>
                    <ClearableSelect v-model="params.action_is_accurate" placeholder="Any" :options="booleanOptions" />
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-medium text-muted-foreground">Area</label>
                    <ClearableSelect v-model="params.action_is_area" placeholder="Any" :options="booleanOptions" />
                </div>
            </div>
        </CollapsibleContent>
    </Collapsible>

    <!-- Abilities -->
    <Collapsible :open="sectionsOpen.abilities" @update:open="emit('toggle', 'abilities')">
        <CollapsibleTrigger
            class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
        >
            Abilities
            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.abilities }" />
        </CollapsibleTrigger>
        <CollapsibleContent class="space-y-3 px-1 pt-3">
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Ability Name</label>
                <Input v-model="params.ability_name" placeholder="e.g. Tough" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Description</label>
                <Input v-model="params.ability_description" placeholder="Match ability body text" />
            </div>
        </CollapsibleContent>
    </Collapsible>

    <!-- Triggers -->
    <Collapsible :open="sectionsOpen.triggers" @update:open="emit('toggle', 'triggers')">
        <CollapsibleTrigger
            class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
        >
            Triggers
            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.triggers }" />
        </CollapsibleTrigger>
        <CollapsibleContent class="space-y-3 px-1 pt-3">
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Trigger Name</label>
                <Input v-model="params.trigger_name" placeholder="e.g. Critical Strike" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Suits</label>
                <Input v-model="params.trigger_suits" placeholder="e.g. R, M, MM" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Description</label>
                <Input v-model="params.trigger_description" placeholder="Match trigger body text" />
            </div>
        </CollapsibleContent>
    </Collapsible>

    <!-- Has flags -->
    <Collapsible :open="sectionsOpen.has" @update:open="emit('toggle', 'has')">
        <CollapsibleTrigger
            class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
        >
            Has…
            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.has }" />
        </CollapsibleTrigger>
        <CollapsibleContent class="space-y-2 px-1 pt-3">
            <p class="text-[11px] text-muted-foreground">Quick presence checks — narrow the unit pool by structural traits.</p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="flag in hasFlags"
                    :key="flag"
                    type="button"
                    :class="[
                        'rounded-md px-2 py-0.5 text-[11px] capitalize transition-colors',
                        activeHasFlags.includes(flag) ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground hover:bg-accent',
                    ]"
                    @click="emit('toggleHas', flag)"
                >
                    {{ flag.replace('_', ' ') }}
                </button>
            </div>
            <div v-if="activeHasFlags.length" class="flex flex-wrap gap-1 pt-1">
                <Badge v-for="f in activeHasFlags" :key="f" variant="outline" class="text-[10px] capitalize">{{ f.replace('_', ' ') }}</Badge>
            </div>
        </CollapsibleContent>
    </Collapsible>

    <!-- Sorting -->
    <Collapsible :open="sectionsOpen.sorting" @update:open="emit('toggle', 'sorting')">
        <CollapsibleTrigger
            class="flex w-full items-center justify-between rounded-md bg-secondary px-3 py-2 text-sm font-medium hover:bg-secondary/80"
        >
            Sorting
            <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': sectionsOpen.sorting }" />
        </CollapsibleTrigger>
        <CollapsibleContent class="space-y-3 px-1 pt-3">
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Sort By</label>
                <ClearableSelect v-model="params.sort" placeholder="Name" :options="sortOptions" />
            </div>
            <div class="space-y-1">
                <label class="text-xs font-medium text-muted-foreground">Direction</label>
                <ClearableSelect v-model="params.sort_type" placeholder="Ascending" :options="sortTypes" />
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
