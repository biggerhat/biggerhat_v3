<script setup lang="ts">
import GameTextEditor from '@/components/CardCreator/GameTextEditor.vue';
import IconTokenPalette from '@/components/CardCreator/IconTokenPalette.vue';
import type { ContentBlock, EnumOption } from '@/components/CardCreator/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useLatestFetch } from '@/composables/useLatestFetch';
import { ChevronDown, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

/** The Card Content card of the upgrade editor: ordered text / ability / action / trigger blocks. Edits the parent's array in place. */
defineProps<{ enums: { suits: EnumOption[]; action_types: EnumOption[] } }>();
const blocks = defineModel<ContentBlock[]>({ required: true });

const fetchLatest = useLatestFetch();

// Content block helpers
const addTextBlock = () => blocks.value.push({ type: 'text', text: '' });

const addAbilityBlock = () =>
    blocks.value.push({
        type: 'ability',
        data: { name: '', suits: null, defensive_ability_type: null, costs_stone: false, description: null, source_id: null },
    });

const addActionBlock = () =>
    blocks.value.push({
        type: 'action',
        data: {
            name: '',
            type: 'tactical',
            is_signature: false,
            stone_cost: 0,
            range: null,
            range_type: null,
            stat: null,
            stat_suits: null,
            stat_modifier: null,
            resisted_by: null,
            target_number: null,
            target_suits: null,
            damage: null,
            description: null,
            source_id: null,
            triggers: [],
        },
    });

const addTriggerBlock = () =>
    blocks.value.push({
        type: 'trigger',
        data: { name: '', suits: null, stone_cost: 0, description: null, source_id: null },
    });

const removeBlock = (index: number) => blocks.value.splice(index, 1);
const moveBlockUp = (index: number) => {
    if (index > 0) {
        const item = blocks.value.splice(index, 1)[0];
        blocks.value.splice(index - 1, 0, item);
    }
};
const moveBlockDown = (index: number) => {
    if (index < blocks.value.length - 1) {
        const item = blocks.value.splice(index, 1)[0];
        blocks.value.splice(index + 1, 0, item);
    }
};

// Search
const searchResults = ref<any[]>([]);
const searchType = ref<'action' | 'ability' | 'trigger' | null>(null);
let searchDebounce: ReturnType<typeof setTimeout>;

const searchOfficial = (type: 'action' | 'ability' | 'trigger', q: string) => {
    searchType.value = type;
    clearTimeout(searchDebounce);
    if (q.length < 2) {
        searchResults.value = [];
        return;
    }
    searchDebounce = setTimeout(async () => {
        const endpoints: Record<string, string> = {
            action: 'api.card-creator.actions',
            ability: 'api.card-creator.abilities',
            trigger: 'api.card-creator.triggers',
        };
        searchResults.value = (await fetchLatest<any[]>('official', route(endpoints[type]) + '?q=' + encodeURIComponent(q))) ?? [];
    }, 300);
};

const pickSearchResult = (official: any) => {
    if (searchType.value === 'ability') {
        blocks.value.push({ type: 'ability', data: { ...official } });
    } else if (searchType.value === 'action') {
        blocks.value.push({ type: 'action', data: { ...official, triggers: official.triggers ?? [] } });
    } else if (searchType.value === 'trigger') {
        blocks.value.push({ type: 'trigger', data: { ...official } });
    }
    searchResults.value = [];
    searchType.value = null;
};

const blockTypeLabel = (type: string) => {
    const map: Record<string, string> = { text: 'Text', ability: 'Ability', action: 'Action', trigger: 'Trigger' };
    return map[type] ?? type;
};

// Alternates a lighter/darker shade of the type's tint by list position
// (idx, across the whole mixed-type list — same treatment CardFrontFace.vue
// gives the rendered card face via alternatingBoxBg) so adjacent blocks are
// visually distinguishable even when they're the same type back-to-back.
const blockTypeColor = (type: string, idx: number) => {
    const even = idx % 2 === 0;
    const map: Record<string, string> = {
        text: 'bg-muted',
        ability: even ? 'bg-blue-500/10 border-blue-500/20' : 'bg-blue-500/5 border-blue-500/15',
        action: even ? 'bg-amber-500/10 border-amber-500/20' : 'bg-amber-500/5 border-amber-500/15',
        trigger: even ? 'bg-purple-500/10 border-purple-500/20' : 'bg-purple-500/5 border-purple-500/15',
    };
    return map[type] ?? 'bg-muted';
};
</script>

<template>
    <Collapsible default-open>
        <Card>
            <CardContent class="p-4">
                <CollapsibleTrigger class="flex w-full items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold">Card Content</h2>
                        <Badge v-if="blocks.length" variant="secondary" class="text-2xs">{{ blocks.length }}</Badge>
                    </div>
                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div class="mt-3 space-y-3">
                        <p class="text-[11px] text-muted-foreground">
                            Add text prefaces and game elements in the order they should appear on the card.
                        </p>

                        <!-- Search official -->
                        <div class="relative">
                            <Input
                                placeholder="Search official abilities, actions, or triggers..."
                                class="h-8 text-xs"
                                @input="(e: Event) => searchOfficial('ability', (e.target as HTMLInputElement).value)"
                            />
                            <div v-if="searchResults.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                <button
                                    v-for="r in searchResults"
                                    :key="r.id"
                                    class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                    @click="pickSearchResult(r)"
                                >
                                    <span class="font-medium">{{ r.name }}</span>
                                    <span v-if="r.description" class="ml-2 text-xs text-muted-foreground">{{ r.description?.slice(0, 50) }}...</span>
                                </button>
                            </div>
                        </div>

                        <!-- Add buttons -->
                        <div class="flex flex-wrap gap-1.5">
                            <Button variant="outline" size="sm" class="h-7 text-[11px]" @click="addTextBlock"
                                ><Plus class="mr-1 size-3" /> Text</Button
                            >
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7 border-blue-500/30 text-[11px] text-blue-600 dark:text-blue-400"
                                @click="addAbilityBlock"
                                ><Plus class="mr-1 size-3" /> Ability</Button
                            >
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7 border-amber-500/30 text-[11px] text-amber-800 dark:text-amber-400"
                                @click="addActionBlock"
                                ><Plus class="mr-1 size-3" /> Action</Button
                            >
                            <Button
                                variant="outline"
                                size="sm"
                                class="h-7 border-purple-500/30 text-[11px] text-purple-600 dark:text-purple-400"
                                @click="addTriggerBlock"
                                ><Plus class="mr-1 size-3" /> Trigger</Button
                            >
                        </div>

                        <!-- Block list -->
                        <div
                            v-for="(block, idx) in blocks"
                            :key="'block-' + idx"
                            class="rounded-lg border p-3"
                            :class="blockTypeColor(block.type, idx)"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="flex flex-col">
                                        <button
                                            aria-label="Move block up"
                                            class="text-muted-foreground hover:text-foreground disabled:opacity-30"
                                            :disabled="idx === 0"
                                            @click="moveBlockUp(idx)"
                                        >
                                            <ChevronDown class="size-3 rotate-180" />
                                        </button>
                                        <button
                                            aria-label="Move block down"
                                            class="text-muted-foreground hover:text-foreground disabled:opacity-30"
                                            :disabled="idx === blocks.length - 1"
                                            @click="moveBlockDown(idx)"
                                        >
                                            <ChevronDown class="size-3" />
                                        </button>
                                    </div>
                                    <Badge variant="outline" class="text-2xs">{{ blockTypeLabel(block.type) }}</Badge>
                                    <span v-if="block.data?.source_id" class="text-2xs text-muted-foreground">(Official)</span>
                                </div>
                                <button aria-label="Remove block" class="text-muted-foreground hover:text-destructive" @click="removeBlock(idx)">
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>

                            <!-- Text block -->
                            <div v-if="block.type === 'text'">
                                <GameTextEditor
                                    v-model="block.text"
                                    placeholder="e.g. This model gains the following ability:"
                                    class="text-xs italic"
                                />
                            </div>

                            <!-- Ability block -->
                            <div v-else-if="block.type === 'ability'" class="space-y-2">
                                <template v-if="block.data?.source_id">
                                    <div class="text-sm font-medium">{{ block.data.name }}</div>
                                    <div v-if="block.data.description" class="text-xs text-muted-foreground">
                                        {{ block.data.description }}
                                    </div>
                                </template>
                                <template v-else>
                                    <Input v-model="block.data!.name" placeholder="Ability name" class="h-7 text-sm font-medium" />
                                    <div class="grid gap-2 sm:grid-cols-3">
                                        <Select v-model="block.data!.suits">
                                            <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Suit" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">None</SelectItem>
                                                <SelectItem v-for="s in enums.suits" :key="s.value" :value="s.value">{{ s.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <Select v-model="block.data!.defensive_ability_type">
                                            <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Defense type" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">None</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <label class="flex items-center gap-1.5 text-xs">
                                            <input v-model="block.data!.costs_stone" type="checkbox" class="rounded" />
                                            Costs Soulstone
                                        </label>
                                    </div>
                                    <GameTextEditor
                                        v-model="block.data!.description"
                                        placeholder="Ability description..."
                                        multiline
                                        :rows="2"
                                        class="text-xs"
                                    />
                                    <IconTokenPalette class="mt-1" />
                                </template>
                            </div>

                            <!-- Action block -->
                            <div v-else-if="block.type === 'action'" class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <Input v-model="block.data!.name" placeholder="Action name" class="h-7 flex-1 text-sm font-medium" />
                                    <Badge v-if="block.data?.source_id" variant="outline" class="shrink-0 px-1 py-0 text-2xs">Official</Badge>
                                </div>
                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                    <div>
                                        <label class="text-2xs text-muted-foreground">Type</label>
                                        <Select v-model="block.data!.type">
                                            <SelectTrigger class="h-7 text-xs"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="t in enums.action_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <label class="text-2xs text-muted-foreground">Range</label>
                                        <Input v-model="block.data!.range" placeholder="e.g. 2, *, X" class="h-7 text-xs" />
                                    </div>
                                    <div>
                                        <label class="text-2xs text-muted-foreground">Stat</label>
                                        <Input v-model="block.data!.stat" placeholder="e.g. 5, X" class="h-7 text-xs" />
                                    </div>
                                    <div>
                                        <label class="text-2xs text-muted-foreground">Resisted By</label>
                                        <Input v-model="block.data!.resisted_by" placeholder="Df" class="h-7 text-xs" />
                                    </div>
                                    <div>
                                        <label class="text-2xs text-muted-foreground">TN</label>
                                        <Input v-model="block.data!.target_number" placeholder="e.g. 12" class="h-7 text-xs" />
                                    </div>
                                    <div>
                                        <label class="text-2xs text-muted-foreground">Damage</label>
                                        <Input v-model="block.data!.damage" placeholder="2/3/5" class="h-7 text-xs" />
                                    </div>
                                </div>
                                <GameTextEditor
                                    v-model="block.data!.description"
                                    placeholder="Action description..."
                                    multiline
                                    :rows="2"
                                    class="text-xs"
                                />
                                <IconTokenPalette class="mt-1" />
                                <label class="flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                    <Checkbox :checked="block.data!.is_signature" @update:checked="(v: boolean) => (block.data!.is_signature = v)" />
                                    Signature action
                                </label>
                            </div>

                            <!-- Trigger block -->
                            <div v-else-if="block.type === 'trigger'" class="space-y-2">
                                <template v-if="block.data?.source_id">
                                    <div class="text-sm font-medium">
                                        <span v-if="block.data.suits" class="text-muted-foreground">{{ block.data.suits }}</span>
                                        {{ block.data.name }}
                                    </div>
                                    <div v-if="block.data.description" class="text-xs text-muted-foreground">
                                        {{ block.data.description }}
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="flex items-center gap-2">
                                        <Input v-model="block.data!.name" placeholder="Trigger name" class="h-7 text-sm font-medium" />
                                        <Input v-model="block.data!.suits" placeholder="Suits" class="h-7 w-28 text-xs" />
                                    </div>
                                    <GameTextEditor v-model="block.data!.description" placeholder="Trigger description" class="text-xs" />
                                </template>
                            </div>
                        </div>
                    </div>
                </CollapsibleContent>
            </CardContent>
        </Card>
    </Collapsible>
</template>
