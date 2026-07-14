<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import TextBar from '@/components/TextBar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface CatalogItem {
    id: number;
    name: string;
}

interface SelectOpt {
    value: string;
    name: string;
}

interface UpgradeableRow {
    type: 'action' | 'ability' | 'trigger' | '';
    id: string | null;
    restriction: string | null;
    is_signature: boolean;
    borrow_exclusion: string | null;
}

const props = defineProps<{
    upgrade?: Record<string, any> | null;
    characters?: SelectOpt[];
    all_characters?: { value: number; name: string }[];
    characteristics?: { value: string; name: string }[];
    factions?: SelectOpt[];
    keywords?: SelectOpt[];
    tokens?: CatalogItem[];
    markers?: CatalogItem[];
    actions?: CatalogItem[];
    abilities?: CatalogItem[];
    triggers?: CatalogItem[];
    crew_upgrade_restrictions?: SelectOpt[];
    borrow_exclusion_options?: SelectOpt[];
    upgradeable_rows?: UpgradeableRow[];
    hiring_rules_fields?: Record<string, any> | null;
    game_mode_types?: SelectOpt[];
}>();

const formInfo = ref({
    game_mode_type: 'standard',
    name: null as string | null,
    faction: null as string | null,
    description: null as string | null,
    power_bar_count: null as number | null,
    front_image: null as File | null,
    back_image: null as File | null,
    tokens: [] as string[],
    markers: [] as string[],
    characters: [] as string[],
    keywords: [] as string[],
    upgradeable_rows: [] as UpgradeableRow[],
    hiring_rules_type: null as string | null,
    alternate_leader: null as string | null,
    any_faction: false,
    fixed_crew_keyword: null as string | null,
    fixed_cache: null as number | null,
    required_characteristic: null as string | null,
    required_count: null as number | null,
});

const addRow = () => {
    formInfo.value.upgradeable_rows.push({ type: '', id: null, restriction: null, is_signature: false, borrow_exclusion: null });
};

const removeRow = (index: number) => {
    formInfo.value.upgradeable_rows.splice(index, 1);
};

const itemOptionsForRow = computed(() => (row: UpgradeableRow) => {
    if (row.type === 'action') return props.actions ?? [];
    if (row.type === 'ability') return props.abilities ?? [];
    if (row.type === 'trigger') return props.triggers ?? [];
    return [];
});

const submit = () => {
    router.post(props.upgrade ? route('admin.crews.update', props.upgrade.slug) : route('admin.crews.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.game_mode_type = props.upgrade?.game_mode_type ?? 'standard';
    formInfo.value.name = props.upgrade?.name ?? null;
    formInfo.value.faction = props.upgrade?.faction ?? null;
    formInfo.value.description = props.upgrade?.description ?? null;
    formInfo.value.power_bar_count = props.upgrade?.power_bar_count ?? null;

    props.upgrade?.markers?.forEach((m: CatalogItem) => formInfo.value.markers.push(m.name));
    props.upgrade?.tokens?.forEach((t: CatalogItem) => formInfo.value.tokens.push(t.name));
    props.upgrade?.characters?.forEach((c: any) => formInfo.value.characters.push(c.display_name));
    props.upgrade?.keywords?.forEach((k: any) => formInfo.value.keywords.push(k.name));

    // Populate row builder from the server-decomposed upgradeable_rows prop.
    if (props.upgradeable_rows?.length) {
        formInfo.value.upgradeable_rows = props.upgradeable_rows.map((r) => ({
            type: r.type,
            id: r.id != null ? String(r.id) : null,
            restriction: r.restriction ?? null,
            is_signature: r.is_signature ?? false,
            borrow_exclusion: r.borrow_exclusion ?? null,
        }));
    }

    const hrf = props.hiring_rules_fields;
    if (hrf) {
        formInfo.value.hiring_rules_type = hrf.hiring_rules_type ?? null;
        formInfo.value.alternate_leader = hrf.alternate_leader != null ? String(hrf.alternate_leader) : null;
        formInfo.value.any_faction = hrf.any_faction ?? false;
        formInfo.value.fixed_crew_keyword = hrf.fixed_crew_keyword ?? null;
        formInfo.value.fixed_cache = hrf.fixed_cache ?? null;
        formInfo.value.required_characteristic = hrf.required_characteristic ?? null;
        formInfo.value.required_count = hrf.required_count ?? null;
    }
});
</script>

<template>
    <Head title="Crew Card Form" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>Crew Card</CardTitle>
                <CardDescription>Create and Edit Crew Card Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="game_mode_type">Game Mode</Label>
                            <Select id="game_mode_type" v-model="formInfo.game_mode_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Game Mode Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="mode in props.game_mode_types" :key="mode.value" :value="mode.value">
                                        {{ mode.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="usePage().props.errors.game_mode_type" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" autofocus placeholder="Crew Card Name" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="faction">Faction</Label>
                                    <Select id="faction" v-model="formInfo.faction">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Crew Card Faction" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="faction in props.factions" :key="faction.value" :value="faction.value">
                                                {{ faction.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="usePage().props.errors.faction" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="characters">Linked Characters</Label>
                                    <SearchableMultiselect
                                        v-model="formInfo.characters"
                                        placeholder="Select Characters"
                                        :options="props.all_characters ?? []"
                                        option-value="name"
                                        class="my-auto"
                                    />
                                    <InputError :message="usePage().props.errors.characters" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="keywords">Keywords</Label>
                                    <SearchableMultiselect
                                        v-model="formInfo.keywords"
                                        placeholder="Select Keywords"
                                        :options="props.keywords ?? []"
                                        option-value="name"
                                        class="my-auto"
                                    />
                                    <InputError :message="usePage().props.errors.keywords" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="power_bar_count">Power Bar Count</Label>
                                    <Input
                                        id="power_bar_count"
                                        v-model="formInfo.power_bar_count"
                                        type="number"
                                        placeholder="Power Bar Count (Optional)"
                                    />
                                    <InputError :message="usePage().props.errors.power_bar_count" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="description">Upgrade Text</Label>
                            <Textarea id="description" v-model="formInfo.description" placeholder="Type the upgrade text here." />
                            <InputError :message="usePage().props.errors.description" />
                        </div>

                        <TextBar text="Images" />
                        <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                            <div class="mx-auto flex w-full max-w-sm flex-col items-center gap-1.5 space-y-1.5">
                                <Label v-if="props.upgrade?.front_image && !formInfo.front_image" for="current_front_image">Current Image</Label>
                                <img
                                    v-if="props.upgrade?.front_image && !formInfo.front_image"
                                    id="current_front_image"
                                    :src="'/storage/' + props.upgrade.front_image"
                                    :alt="props.upgrade.name"
                                    class="h-full w-full rounded-lg"
                                />
                                <Label for="front_image">Front of Card Image</Label>
                                <Input
                                    id="front_image"
                                    type="file"
                                    accept=".heic,.jpeg,.jpg,.png,.webp"
                                    @input="formInfo.front_image = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                />
                                <InputError :message="usePage().props.errors.front_image" />
                            </div>
                            <div class="mx-auto flex w-full max-w-sm flex-col items-center gap-1.5 space-y-1.5">
                                <Label v-if="props.upgrade?.back_image && !formInfo.back_image" for="current_back_image">Current Image</Label>
                                <img
                                    v-if="props.upgrade?.back_image && !formInfo.back_image"
                                    id="current_back_image"
                                    :src="'/storage/' + props.upgrade.back_image"
                                    :alt="props.upgrade.name"
                                    class="h-full w-full rounded-lg"
                                />
                                <Label for="back_image">Back of Card Image</Label>
                                <Input
                                    id="back_image"
                                    type="file"
                                    accept=".heic,.jpeg,.jpg,.png,.webp"
                                    @input="formInfo.back_image = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                />
                                <InputError :message="usePage().props.errors.back_image" />
                            </div>
                        </div>

                        <TextBar text="Related" />
                        <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                            <div class="flex flex-col space-y-1.5">
                                <SearchableMultiselect
                                    v-model="formInfo.markers"
                                    placeholder="Select Markers"
                                    :options="props.markers ?? []"
                                    option-value="name"
                                />
                                <InputError :message="usePage().props.errors.markers" />
                            </div>
                            <div class="flex flex-col space-y-1.5">
                                <SearchableMultiselect
                                    v-model="formInfo.tokens"
                                    placeholder="Select Tokens"
                                    :options="props.tokens ?? []"
                                    option-value="name"
                                />
                                <InputError :message="usePage().props.errors.tokens" />
                            </div>
                        </div>

                        <TextBar text="Actions / Abilities / Triggers" />
                        <div class="space-y-2">
                            <div
                                v-for="(row, i) in formInfo.upgradeable_rows"
                                :key="i"
                                class="flex flex-wrap items-start gap-2 rounded-md border p-3"
                            >
                                <!-- Type -->
                                <div class="w-32 shrink-0">
                                    <Select
                                        :model-value="row.type"
                                        @update:model-value="
                                            (v) => {
                                                row.type = v as UpgradeableRow['type'];
                                                row.id = null;
                                            }
                                        "
                                    >
                                        <SelectTrigger class="h-8 text-xs">
                                            <SelectValue placeholder="Type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="action">Action</SelectItem>
                                            <SelectItem value="ability">Ability</SelectItem>
                                            <SelectItem value="trigger">Trigger</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Item -->
                                <div class="min-w-0 flex-1">
                                    <SearchableSelect
                                        v-model="row.id"
                                        :options="itemOptionsForRow(row)"
                                        option-value="id"
                                        option-label="name"
                                        :placeholder="row.type ? `Search ${row.type}s…` : 'Pick type first'"
                                    />
                                </div>

                                <!-- Restriction -->
                                <div class="w-56 shrink-0">
                                    <Select :model-value="row.restriction ?? ''" @update:model-value="(v) => (row.restriction = v || null)">
                                        <SelectTrigger class="h-8 text-xs">
                                            <SelectValue placeholder="No restriction" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">No restriction</SelectItem>
                                            <SelectItem v-for="opt in props.crew_upgrade_restrictions" :key="opt.value" :value="opt.value">
                                                {{ opt.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Tier-4 borrow exclusion (pg 32, 54) -->
                                <div class="w-56 shrink-0">
                                    <Select
                                        :model-value="row.borrow_exclusion ?? ''"
                                        @update:model-value="(v) => (row.borrow_exclusion = v || null)"
                                    >
                                        <SelectTrigger class="h-8 text-xs">
                                            <SelectValue placeholder="Eligible to borrow" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Eligible to borrow</SelectItem>
                                            <SelectItem v-for="opt in props.borrow_exclusion_options" :key="opt.value" :value="opt.value">
                                                Excluded — {{ opt.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <!-- Signature toggle (actions only) -->
                                <div v-if="row.type === 'action'" class="flex shrink-0 items-center gap-1.5">
                                    <Checkbox :id="`sig-${i}`" :checked="row.is_signature" @update:checked="(v: boolean) => (row.is_signature = v)" />
                                    <label :for="`sig-${i}`" class="cursor-pointer text-xs">Signature</label>
                                </div>

                                <!-- Remove -->
                                <button type="button" class="shrink-0 text-muted-foreground hover:text-destructive" @click="removeRow(i)">
                                    <X class="h-4 w-4" />
                                </button>
                            </div>

                            <Button type="button" variant="outline" size="sm" @click="addRow">+ Add Row</Button>
                        </div>

                        <TextBar text="Hiring Rules" />
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5 md:col-span-2">
                                    <Label for="hiring_rules_type">Rule Type</Label>
                                    <Select id="hiring_rules_type" v-model="formInfo.hiring_rules_type">
                                        <SelectTrigger>
                                            <SelectValue placeholder="None (Normal Upgrade)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem :value="null">None</SelectItem>
                                            <SelectItem value="fixed_crew">Fixed Crew (On Tour style)</SelectItem>
                                            <SelectItem value="required_hires">Required Hires (Riders of Fate style)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <template v-if="formInfo.hiring_rules_type === 'fixed_crew'">
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="alternate_leader">Alternate Leader</Label>
                                        <Select id="alternate_leader" v-model="formInfo.alternate_leader">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select Alternate Leader" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="char in props.all_characters" :key="char.value" :value="String(char.value)">
                                                    {{ char.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="usePage().props.errors.alternate_leader" />
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="fixed_crew_keyword">Fixed Crew Keyword (slug)</Label>
                                        <Input id="fixed_crew_keyword" v-model="formInfo.fixed_crew_keyword" placeholder="e.g. crossroads" />
                                        <InputError :message="usePage().props.errors.fixed_crew_keyword" />
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="fixed_cache">Fixed Soulstone Cache</Label>
                                        <Input id="fixed_cache" v-model="formInfo.fixed_cache" type="number" placeholder="e.g. 6" />
                                        <InputError :message="usePage().props.errors.fixed_cache" />
                                    </div>
                                    <div class="flex items-center space-x-2 self-end">
                                        <input id="any_faction" v-model="formInfo.any_faction" type="checkbox" class="rounded border-gray-300" />
                                        <Label for="any_faction">Any Faction</Label>
                                    </div>
                                </template>

                                <template v-if="formInfo.hiring_rules_type === 'required_hires'">
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="required_characteristic">Required Characteristic</Label>
                                        <Select id="required_characteristic" v-model="formInfo.required_characteristic">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select Characteristic" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="char in props.characteristics" :key="char.value" :value="char.value">
                                                    {{ char.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="usePage().props.errors.required_characteristic" />
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="required_count">Required Count</Label>
                                        <Input id="required_count" v-model="formInfo.required_count" type="number" placeholder="e.g. 4" />
                                        <InputError :message="usePage().props.errors.required_count" />
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-end gap-2 px-6 pb-6">
                <Button @click="router.get(route('admin.crews.index'))" variant="outline">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
