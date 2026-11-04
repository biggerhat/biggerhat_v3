<script setup lang="ts">
import ActionLookupModal from '@/components/CardCreator/ActionLookupModal.vue';
import GameTextEditor from '@/components/CardCreator/GameTextEditor.vue';
import IconTokenPalette from '@/components/CardCreator/IconTokenPalette.vue';
import type { ActionData, EnumOption } from '@/components/CardCreator/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useLatestFetch } from '@/composables/useLatestFetch';
import { ChevronDown, Plus, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';

/** The Actions card of the character editor: official search, Browse modal, custom action boxes and their triggers. Edits the parent's array in place. */
defineProps<{ enums: { action_types: EnumOption[]; range_types: EnumOption[] } }>();
const actions = defineModel<ActionData[]>({ required: true });

const fetchLatest = useLatestFetch();
const results = ref<any[]>([]);
const triggerResults = ref<any[]>([]);
const triggerTarget = ref<number | null>(null);
const modalOpen = ref(false);
let debounce: ReturnType<typeof setTimeout>;
let triggerDebounce: ReturnType<typeof setTimeout>;

const search = (q: string) => {
    clearTimeout(debounce);
    if (q.length < 2) {
        results.value = [];
        return;
    }
    debounce = setTimeout(async () => {
        const data = await fetchLatest<any[]>('action', route('api.card-creator.actions') + '?q=' + encodeURIComponent(q));
        if (data) results.value = data;
    }, 300);
};

// Triggers are searched per action (the box that was typed in), and added to that action only.
const searchTriggers = (q: string, actionIndex: number) => {
    triggerTarget.value = actionIndex;
    clearTimeout(triggerDebounce);
    if (q.length < 2) {
        triggerResults.value = [];
        return;
    }
    triggerDebounce = setTimeout(async () => {
        const data = await fetchLatest<any[]>('trigger', route('api.card-creator.triggers') + '?q=' + encodeURIComponent(q));
        if (data) triggerResults.value = data;
    }, 300);
};

const pickTrigger = (official: any) => {
    if (triggerTarget.value !== null && actions.value[triggerTarget.value]) {
        actions.value[triggerTarget.value].triggers.push({ ...official });
    }
    triggerResults.value = [];
    triggerTarget.value = null;
};

// Official actions come in without their triggers — those are added separately via the per-action trigger search.
const pick = (official: any) => {
    actions.value.push({ ...official, triggers: [] });
    results.value = [];
};

const addAction = () => {
    actions.value.push({
        name: '',
        type: 'attack',
        is_signature: false,
        stone_cost: 0,
        range: null,
        range_type: 'none',
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
    });
};
const removeAction = (index: number) => actions.value.splice(index, 1);

const addTrigger = (action: ActionData) => {
    action.triggers.push({ name: '', suits: null, stone_cost: 0, description: null, source_id: null });
};
const removeTrigger = (action: ActionData, index: number) => action.triggers.splice(index, 1);

const commitFromModal = (picked: ActionData[]) => {
    for (const a of picked) actions.value.push({ ...a, triggers: [] });
};
</script>

<template>
    <Collapsible default-open>
        <Card>
            <CardContent class="p-4">
                <CollapsibleTrigger class="flex w-full items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold">Actions</h3>
                        <Badge v-if="actions.length" variant="secondary" class="text-2xs">{{ actions.length }}</Badge>
                    </div>
                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div class="mt-3 space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <Input
                                    placeholder="Search official actions..."
                                    class="h-8 text-xs"
                                    @input="(e: Event) => search((e.target as HTMLInputElement).value)"
                                />
                                <div v-if="results.length" class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md">
                                    <button
                                        v-for="r in results"
                                        :key="r.id"
                                        class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                        @click="pick(r)"
                                    >
                                        <span class="font-medium">{{ r.name }}</span>
                                        <Badge class="ml-2 text-2xs">{{ r.type }}</Badge>
                                    </button>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="modalOpen = true"
                                ><Plus class="mr-1 size-3" /> Browse</Button
                            >
                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="addAction"
                                ><Plus class="mr-1 size-3" /> Custom</Button
                            >
                        </div>

                        <!-- Alternates a lighter/darker amber shade by list position — see the
                             matching comment on the Abilities boxes above. -->
                        <div
                            v-for="(action, idx) in actions"
                            :key="'action-' + idx"
                            class="space-y-3 rounded-lg border p-3"
                            :class="idx % 2 === 0 ? 'border-amber-500/20 bg-amber-500/10' : 'border-amber-500/15 bg-amber-500/5'"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex flex-1 items-center gap-2">
                                    <Input v-model="action.name" placeholder="Action name" class="h-7 text-sm font-medium" />
                                    <Badge v-if="action.source_id" variant="outline" class="shrink-0 px-1 py-0 text-2xs">Official</Badge>
                                </div>
                                <button
                                    aria-label="Remove action"
                                    class="ml-2 shrink-0 text-muted-foreground hover:text-destructive"
                                    @click="removeAction(idx)"
                                >
                                    <Trash2 class="size-3.5" />
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                <div>
                                    <label class="text-2xs text-muted-foreground">Type</label>
                                    <Select v-model="action.type">
                                        <SelectTrigger class="h-7 text-xs"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="t in enums.action_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <label class="text-2xs text-muted-foreground">Range</label>
                                    <Input v-model="action.range" placeholder="e.g. 2, *, X" class="h-7 text-xs" />
                                </div>
                                <div>
                                    <label class="text-2xs text-muted-foreground">Range Type</label>
                                    <Select v-model="action.range_type">
                                        <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="—" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">None</SelectItem>
                                            <SelectItem v-for="r in enums.range_types" :key="r.value" :value="r.value">{{ r.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div>
                                    <label class="text-2xs text-muted-foreground">Stat</label>
                                    <Input v-model="action.stat" placeholder="e.g. 5, X" class="h-7 text-xs" />
                                </div>
                                <div>
                                    <label class="text-2xs text-muted-foreground">Resisted By</label>
                                    <Input v-model="action.resisted_by" placeholder="Df" class="h-7 text-xs" />
                                </div>
                                <div>
                                    <label class="text-2xs text-muted-foreground">Damage</label>
                                    <Input v-model="action.damage" placeholder="2/3/5" class="h-7 text-xs" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                <div>
                                    <label class="text-2xs text-muted-foreground">Stat Suits</label>
                                    <Input v-model="action.stat_suits" placeholder="e.g. crow ram" class="h-7 text-xs" />
                                </div>
                                <div>
                                    <label class="text-2xs text-muted-foreground">Stat Modifier</label>
                                    <Input v-model="action.stat_modifier" placeholder="e.g. crow" class="h-7 text-xs" />
                                </div>
                                <div>
                                    <label class="text-2xs text-muted-foreground">Target #</label>
                                    <Input v-model="action.target_number" placeholder="e.g. 13" class="h-7 text-xs" />
                                </div>
                                <div>
                                    <label class="text-2xs text-muted-foreground">Target Suits</label>
                                    <Input v-model="action.target_suits" placeholder="e.g. crow" class="h-7 text-xs" />
                                </div>
                            </div>
                            <GameTextEditor v-model="action.description" placeholder="Action description..." multiline :rows="2" class="text-xs" />
                            <IconTokenPalette class="mt-1" />
                            <label class="flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                <Checkbox :checked="action.is_signature" @update:checked="(v: boolean) => (action.is_signature = v)" />
                                Signature action
                            </label>

                            <!-- Triggers (always shown) -->
                            <div class="space-y-2 border-t pt-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-medium text-muted-foreground">Triggers</span>
                                    <Button variant="ghost" size="sm" class="h-5 px-1.5 text-2xs" @click="addTrigger(action)"
                                        ><Plus class="mr-0.5 size-2.5" /> Add</Button
                                    >
                                </div>
                                <div class="relative">
                                    <Input
                                        placeholder="Search official triggers..."
                                        class="h-7 text-xs"
                                        @input="(e: Event) => searchTriggers((e.target as HTMLInputElement).value, idx)"
                                    />
                                    <div
                                        v-if="triggerTarget === idx && triggerResults.length"
                                        class="absolute z-10 mt-1 w-full rounded-md border bg-popover p-1 shadow-md"
                                    >
                                        <button
                                            v-for="r in triggerResults"
                                            :key="r.id"
                                            class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                            @click="pickTrigger(r)"
                                        >
                                            <span class="font-medium">{{ r.name }}</span>
                                            <span v-if="r.suits" class="ml-1 text-xs text-muted-foreground">({{ r.suits }})</span>
                                        </button>
                                    </div>
                                </div>
                                <!-- Alternates a lighter/darker purple shade by list position — see
                                     the matching comment on the Abilities boxes above. -->
                                <div
                                    v-for="(trigger, tIdx) in action.triggers"
                                    :key="'trigger-' + tIdx"
                                    class="flex items-start gap-2 rounded border p-2"
                                    :class="tIdx % 2 === 0 ? 'border-purple-500/20 bg-purple-500/10' : 'border-purple-500/15 bg-purple-500/5'"
                                >
                                    <template v-if="trigger.source_id">
                                        <div class="flex-1 text-xs">
                                            <span class="font-medium">{{ trigger.suits }} {{ trigger.name }}:</span>
                                            <span class="text-muted-foreground"> {{ trigger.description }}</span>
                                            <Badge variant="outline" class="ml-1 px-1 py-0 text-2xs">Official</Badge>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div class="flex-1 space-y-1">
                                            <div class="flex items-center gap-2">
                                                <Input v-model="trigger.name" placeholder="Trigger name" class="h-6 text-xs font-medium" />
                                                <Input v-model="trigger.suits" placeholder="Suits" class="h-6 w-24 text-xs" />
                                            </div>
                                            <GameTextEditor v-model="trigger.description" placeholder="Trigger description" class="text-xs" />
                                        </div>
                                    </template>
                                    <button
                                        aria-label="Remove trigger"
                                        class="mt-1 text-muted-foreground hover:text-destructive"
                                        @click="removeTrigger(action, tIdx)"
                                    >
                                        <X class="size-3" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CollapsibleContent>
            </CardContent>
        </Card>
    </Collapsible>
    <ActionLookupModal v-model:open="modalOpen" @commit="commitFromModal" />
</template>
