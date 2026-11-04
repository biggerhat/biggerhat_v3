<script setup lang="ts">
import AbilityLookupModal from '@/components/CardCreator/AbilityLookupModal.vue';
import GameTextEditor from '@/components/CardCreator/GameTextEditor.vue';
import IconTokenPalette from '@/components/CardCreator/IconTokenPalette.vue';
import type { AbilityData, EnumOption } from '@/components/CardCreator/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useLatestFetch } from '@/composables/useLatestFetch';
import { ChevronDown, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

/** The Abilities card of the character editor: official search, Browse modal, and custom ability boxes. Edits the parent's array in place. */
defineProps<{ enums: { suits: EnumOption[]; defensive_ability_types: EnumOption[] } }>();
const abilities = defineModel<AbilityData[]>({ required: true });

const fetchLatest = useLatestFetch();
const results = ref<any[]>([]);
const modalOpen = ref(false);
let debounce: ReturnType<typeof setTimeout>;

const search = (q: string) => {
    clearTimeout(debounce);
    if (q.length < 2) {
        results.value = [];
        return;
    }
    debounce = setTimeout(async () => {
        const data = await fetchLatest<any[]>('ability', route('api.card-creator.abilities') + '?q=' + encodeURIComponent(q));
        if (data) results.value = data;
    }, 300);
};

const pick = (official: any) => {
    abilities.value.push({ ...official });
    results.value = [];
};

const addAbility = () => {
    abilities.value.push({ name: '', suits: 'none', defensive_ability_type: 'none', costs_stone: false, description: null, source_id: null });
};
const removeAbility = (index: number) => abilities.value.splice(index, 1);

const commitFromModal = (picked: AbilityData[]) => {
    for (const a of picked) abilities.value.push({ ...a });
};
</script>

<template>
    <Collapsible default-open>
        <Card>
            <CardContent class="p-4">
                <CollapsibleTrigger class="flex w-full items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-semibold">Abilities</h3>
                        <Badge v-if="abilities.length" variant="secondary" class="text-2xs">{{ abilities.length }}</Badge>
                    </div>
                    <ChevronDown class="size-4 text-muted-foreground transition-transform [[data-state=open]_&]:rotate-180" />
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <div class="mt-3 space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <Input
                                    placeholder="Search official abilities..."
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
                                        <span class="ml-2 text-xs text-muted-foreground"
                                            >{{ r.description?.slice(0, 60) }}{{ r.description?.length > 60 ? '...' : '' }}</span
                                        >
                                    </button>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="modalOpen = true"
                                ><Plus class="mr-1 size-3" /> Browse</Button
                            >
                            <Button variant="outline" size="sm" class="h-8 shrink-0 text-xs" @click="addAbility"
                                ><Plus class="mr-1 size-3" /> Custom</Button
                            >
                        </div>

                        <!-- Same per-type tint as the Custom Upgrade editor's Card Content
                             blocks (blockTypeColor in UpgradeEditor.vue) — breaks up the
                             Abilities/Actions/Triggers sections visually instead of every
                             block looking identical. Alternates a lighter/darker shade of
                             that tint by list position so adjacent entries are distinguishable. -->
                        <div
                            v-for="(ability, aIdx) in abilities"
                            :key="'ability-' + aIdx"
                            class="space-y-2 rounded-lg border p-3"
                            :class="aIdx % 2 === 0 ? 'border-blue-500/20 bg-blue-500/10' : 'border-blue-500/15 bg-blue-500/5'"
                        >
                            <template v-if="ability.source_id">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-medium">{{ ability.name }}</span>
                                        <Badge variant="outline" class="shrink-0 px-1 py-0 text-2xs">Official</Badge>
                                    </div>
                                    <button
                                        aria-label="Remove ability"
                                        class="text-muted-foreground hover:text-destructive"
                                        @click="removeAbility(aIdx)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                                <div v-if="ability.description" class="text-xs text-muted-foreground">{{ ability.description }}</div>
                            </template>
                            <template v-else>
                                <div class="flex items-center justify-between">
                                    <Input v-model="ability.name" placeholder="Ability name" class="h-7 text-sm font-medium" />
                                    <button
                                        aria-label="Remove ability"
                                        class="ml-2 text-muted-foreground hover:text-destructive"
                                        @click="removeAbility(aIdx)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </button>
                                </div>
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <Select v-model="ability.suits">
                                        <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Suit" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">None</SelectItem>
                                            <SelectItem v-for="s in enums.suits" :key="s.value" :value="s.value">{{ s.name }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Select v-model="ability.defensive_ability_type">
                                        <SelectTrigger class="h-7 text-xs"><SelectValue placeholder="Defense type" /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">None</SelectItem>
                                            <SelectItem v-for="d in enums.defensive_ability_types" :key="d.value" :value="d.value">{{
                                                d.name
                                            }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <label class="flex items-center gap-1.5 text-xs">
                                        <input v-model="ability.costs_stone" type="checkbox" class="rounded" />
                                        Costs Soulstone
                                    </label>
                                </div>
                                <GameTextEditor
                                    v-model="ability.description"
                                    placeholder="Ability description..."
                                    multiline
                                    :rows="2"
                                    class="text-xs"
                                />
                                <IconTokenPalette class="mt-1" />
                            </template>
                        </div>
                    </div>
                </CollapsibleContent>
            </CardContent>
        </Card>
    </Collapsible>
    <AbilityLookupModal v-model:open="modalOpen" @commit="commitFromModal" />
</template>
