<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import TextBar from '@/components/TextBar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { CircleX } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps({
    upgrade: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    keywords: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    factions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    limitations: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    tokens: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    markers: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    triggers: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    actions: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    abilities: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    game_mode_types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    game_mode_type: 'standard',
    name: null,
    faction: null,
    description: null,
    power_bar_count: null,
    front_image: null,
    back_image: null,
    combination_image: null,
    plentiful: null,
    type: null,
    limitations: null,
    tokens: [],
    markers: [],
    triggers: [],
    actions: [],
    signature_actions: [],
    abilities: [],
    characters: [],
    keywords: [],
    // Campaign-only fields — null/false on Standard upgrades. Rendered
    // conditionally below when game_mode_type === 'campaign'.
    campaign_upgrade_kind: null as string | null, // 'equipment' | 'injury'
    // Equipment fields
    campaign_br: null as number | null,
    campaign_cc: null as number | null,
    campaign_pool_suit_a: null as string | null,
    campaign_pool_suit_b: null as string | null,
    campaign_is_always_available: false,
    campaign_ttw_only: false,
    campaign_is_omens_mark: false,
    campaign_is_unique: false,
    campaign_leader_only: false,
    campaign_non_unique_only: false,
    campaign_annihilate_after_game: false,
    campaign_is_red_joker_entry: false,
    // Injury fields
    campaign_flip_value: null as number | null,
    campaign_suit_pool: null as string | null, // 'pc' | 'te' | 'black_joker' | 'red_joker'
    campaign_is_traitor: false,
    campaign_is_close_call: false,
    campaign_annihilates_model: false,
    campaign_reflip_if_no_triggers: false,
    campaign_reflip_if_master_or_totem: false,
});

const submit = () => {
    router.post(props.upgrade ? route('admin.upgrades.update', props.upgrade.slug) : route('admin.upgrades.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.game_mode_type = props.upgrade?.game_mode_type ?? 'standard';
    formInfo.value.name = props.upgrade?.name ?? null;
    formInfo.value.faction = props.upgrade?.faction ?? null;
    formInfo.value.description = props.upgrade?.description ?? null;
    formInfo.value.power_bar_count = props.upgrade?.power_bar_count ?? null;
    formInfo.value.plentiful = props.upgrade?.plentiful ?? 1;
    formInfo.value.limitations = props.upgrade?.limitations ?? null;
    formInfo.value.type = props.upgrade?.type ?? null;

    // Campaign-only — populate from the upgrade if present.
    formInfo.value.campaign_upgrade_kind = props.upgrade?.campaign_upgrade_kind ?? null;
    formInfo.value.campaign_br = props.upgrade?.campaign_br ?? null;
    formInfo.value.campaign_cc = props.upgrade?.campaign_cc ?? null;
    formInfo.value.campaign_pool_suit_a = props.upgrade?.campaign_pool_suit_a ?? null;
    formInfo.value.campaign_pool_suit_b = props.upgrade?.campaign_pool_suit_b ?? null;
    formInfo.value.campaign_is_always_available = props.upgrade?.campaign_is_always_available ?? false;
    formInfo.value.campaign_ttw_only = props.upgrade?.campaign_ttw_only ?? false;
    formInfo.value.campaign_is_omens_mark = props.upgrade?.campaign_is_omens_mark ?? false;
    formInfo.value.campaign_is_unique = props.upgrade?.campaign_is_unique ?? false;
    formInfo.value.campaign_leader_only = props.upgrade?.campaign_leader_only ?? false;
    formInfo.value.campaign_non_unique_only = props.upgrade?.campaign_non_unique_only ?? false;
    formInfo.value.campaign_annihilate_after_game = props.upgrade?.campaign_annihilate_after_game ?? false;
    formInfo.value.campaign_is_red_joker_entry = props.upgrade?.campaign_is_red_joker_entry ?? false;
    formInfo.value.campaign_flip_value = props.upgrade?.campaign_flip_value ?? null;
    formInfo.value.campaign_suit_pool = props.upgrade?.campaign_suit_pool ?? null;
    formInfo.value.campaign_is_traitor = props.upgrade?.campaign_is_traitor ?? false;
    formInfo.value.campaign_is_close_call = props.upgrade?.campaign_is_close_call ?? false;
    formInfo.value.campaign_annihilates_model = props.upgrade?.campaign_annihilates_model ?? false;
    formInfo.value.campaign_reflip_if_no_triggers = props.upgrade?.campaign_reflip_if_no_triggers ?? false;
    formInfo.value.campaign_reflip_if_master_or_totem = props.upgrade?.campaign_reflip_if_master_or_totem ?? false;

    props.upgrade?.markers.forEach((marker) => {
        formInfo.value.markers.push(marker.name);
    });

    props.upgrade?.tokens.forEach((token) => {
        formInfo.value.tokens.push(token.name);
    });

    props.upgrade?.triggers.forEach((trigger) => {
        formInfo.value.triggers.push(trigger.name);
    });

    props.upgrade?.characters.forEach((character) => {
        formInfo.value.characters.push(character.display_name);
    });

    props.upgrade?.keywords.forEach((keyword) => {
        formInfo.value.keywords.push(keyword.name);
    });

    props.upgrade?.actions.forEach((action) => {
        if (action.pivot.is_signature_action) {
            formInfo.value.signature_actions.push(String(action.id));
        } else {
            formInfo.value.actions.push(String(action.id));
        }
    });

    props.upgrade?.abilities.forEach((ability) => {
        formInfo.value.abilities.push(ability.name);
    });
});
</script>

<template>
    <Head title="Upgrade Form" />
    <div class="container mx-auto mt-6 h-full px-2">
        <Card>
            <CardHeader>
                <CardTitle>Upgrade</CardTitle>
                <CardDescription>Create and Edit Upgrade Information</CardDescription>
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
                                    <SelectItem v-for="mode in props.game_mode_types" :value="mode.value" :key="mode.value">
                                        {{ mode.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="usePage().props.errors.game_mode_type" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" autofocus placeholder="Upgrade Name" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="faction">Faction</Label>
                            <Select id="faction" v-model="formInfo.faction">
                                <SelectTrigger>
                                    <SelectValue placeholder="Upgrade Faction" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="faction in props.factions" :value="faction.value" :key="faction.value">
                                        {{ faction.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="usePage().props.errors.faction" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="my-auto flex w-full flex-col space-y-1.5">
                                    <Label for="type">Type (Optional)</Label>
                                    <div class="my-auto flex w-full">
                                        <Select id="type" v-model="formInfo.type" class="inline">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Upgrade Type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="type in props.types" :value="type.value" :key="type.value">
                                                    {{ type.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <CircleX class="my-auto ml-2 text-destructive" v-if="formInfo.type" @click="formInfo.type = null" />
                                    </div>
                                    <InputError :message="usePage().props.errors.type" />
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
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="plentiful">Plentiful Count</Label>
                                    <Input
                                        id="plentiful"
                                        v-model="formInfo.plentiful"
                                        type="number"
                                        min="1"
                                        placeholder="Plentiful Count (Optional)"
                                    />
                                    <InputError :message="usePage().props.errors.plentiful" />
                                </div>
                                <div class="my-auto flex w-full flex-col space-y-1.5">
                                    <Label for="type">Limitations (Optional)</Label>
                                    <div class="my-auto flex w-full">
                                        <Select id="type" v-model="formInfo.limitations" class="inline">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Upgrade Limitation" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="limitation in props.limitations" :value="limitation.value" :key="limitation.value">
                                                    {{ limitation.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <CircleX
                                            class="my-auto ml-2 text-destructive"
                                            v-if="formInfo.limitations"
                                            @click="formInfo.limitations = null"
                                        />
                                    </div>
                                    <InputError :message="usePage().props.errors.limitations" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="description">Upgrade Text</Label>
                                <Textarea id="description" v-model="formInfo.description" placeholder="Type the upgrade text here." />
                                <InputError :message="usePage().props.errors.description" />
                            </div>
                        </div>

                        <TextBar text="Images" />
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="mx-auto flex w-full max-w-sm flex-col items-center gap-1.5 space-y-1.5">
                                    <Label v-if="props.upgrade?.front_image && !formInfo.front_image" for="current_front_image">Current Image</Label>
                                    <img
                                        id="current_front_image"
                                        v-if="props.upgrade?.front_image && !formInfo.front_image"
                                        :src="'/storage/' + props.upgrade?.front_image"
                                        :alt="props.upgrade?.name"
                                        class="h-full w-full rounded-lg"
                                    />
                                    <Label for="front_image">Front of Card Image</Label>
                                    <Input
                                        id="front_image"
                                        type="file"
                                        accept=".heic, .jpeg, .jpg, .png, .webp"
                                        @input="formInfo.front_image = $event.target.files[0]"
                                    />
                                    <InputError :message="usePage().props.errors.front_image" />
                                </div>
                                <div class="mx-auto flex w-full max-w-sm flex-col items-center gap-1.5 space-y-1.5">
                                    <Label v-if="props.upgrade?.back_image && !formInfo.back_image" for="current_back_image">Current Image</Label>
                                    <img
                                        id="current_back_image"
                                        v-if="props.upgrade?.back_image && !formInfo.back_image"
                                        :src="'/storage/' + props.upgrade?.back_image"
                                        :alt="props.upgrade?.name"
                                        class="h-full w-full rounded-lg"
                                    />
                                    <Label for="back_image">Back of Card Image</Label>
                                    <Input
                                        id="back_image"
                                        type="file"
                                        accept=".heic, .jpeg, .jpg, .png, .webp"
                                        @input="formInfo.back_image = $event.target.files[0]"
                                    />
                                    <InputError :message="usePage().props.errors.back_image" />
                                </div>
                            </div>
                        </div>

                        <TextBar text="Related" />
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="keywords">Keywords</Label>
                                    <SearchableMultiselect
                                        v-model="formInfo.keywords"
                                        placeholder="Select Keywords"
                                        :options="props.keywords"
                                        option-value="name"
                                        class="my-auto"
                                    />
                                    <InputError :message="usePage().props.errors.keywords" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <SearchableMultiselect
                                        v-model="formInfo.markers"
                                        placeholder="Select Markers"
                                        :options="props.markers"
                                        option-value="name"
                                    />
                                    <InputError :message="usePage().props.errors.markers" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <SearchableMultiselect
                                        v-model="formInfo.tokens"
                                        placeholder="Select Tokens"
                                        :options="props.tokens"
                                        option-value="name"
                                    />
                                    <InputError :message="usePage().props.errors.tokens" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <SearchableMultiselect
                                        v-model="formInfo.abilities"
                                        placeholder="Select Abilities"
                                        :options="props.abilities"
                                        option-value="name"
                                    />
                                    <InputError :message="usePage().props.errors.abilities" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <SearchableMultiselect
                                        v-model="formInfo.actions"
                                        placeholder="Select Actions"
                                        :options="props.actions"
                                        option-value="id"
                                    />
                                    <InputError :message="usePage().props.errors.actions" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <SearchableMultiselect
                                        v-model="formInfo.signature_actions"
                                        placeholder="Select Signature Actions"
                                        :options="props.actions"
                                        option-value="id"
                                    />
                                    <InputError :message="usePage().props.errors.signature_actions" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <SearchableMultiselect
                                        v-model="formInfo.triggers"
                                        placeholder="Select Triggers"
                                        :options="props.triggers"
                                        option-value="name"
                                    />
                                    <InputError :message="usePage().props.errors.triggers" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <SearchableMultiselect
                                        v-model="formInfo.characters"
                                        placeholder="Select Characters"
                                        :options="props.characters"
                                        option-value="name"
                                    />
                                    <InputError :message="usePage().props.errors.characters" />
                                </div>
                            </div>
                        </div>

                        <!-- Campaign-only fields. Hidden when game_mode_type is
                             Standard; surfaces equipment vs injury subforms when
                             campaign mode is active. The server zeroes campaign_*
                             on non-campaign rows in the controller. -->
                        <fieldset
                            v-if="formInfo.game_mode_type === 'campaign'"
                            class="space-y-3 rounded-md border border-primary/30 bg-primary/5 p-3"
                        >
                            <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Campaign Mode</legend>

                            <div class="flex flex-col space-y-1.5">
                                <Label for="campaign_upgrade_kind">Upgrade Kind</Label>
                                <Select id="campaign_upgrade_kind" v-model="formInfo.campaign_upgrade_kind">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Equipment or Injury" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="equipment">Equipment</SelectItem>
                                        <SelectItem value="injury">Injury</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="text-[11px] text-muted-foreground">
                                    Equipment = barter / loot pool. Injury = applied to a model during the Aftermath Doctor/Determine-Injury phases.
                                </p>
                            </div>

                            <!-- Equipment-specific fields -->
                            <div v-if="formInfo.campaign_upgrade_kind === 'equipment'" class="space-y-3 border-t pt-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="campaign_br">BR (1–13)</Label>
                                        <Input id="campaign_br" type="number" min="1" max="13" v-model.number="formInfo.campaign_br" />
                                        <p class="text-[11px] text-muted-foreground">Barter flip threshold. Leave blank for Always-Available.</p>
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="campaign_cc">Scrip Cost (CC)</Label>
                                        <Input id="campaign_cc" type="number" min="0" v-model.number="formInfo.campaign_cc" />
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="campaign_pool_suit_a">Pool Suit A</Label>
                                        <Input
                                            id="campaign_pool_suit_a"
                                            v-model="formInfo.campaign_pool_suit_a"
                                            placeholder="ram / crow / mask / tome"
                                        />
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="campaign_pool_suit_b">Pool Suit B</Label>
                                        <Input id="campaign_pool_suit_b" v-model="formInfo.campaign_pool_suit_b" />
                                    </div>
                                </div>
                                <div class="grid gap-2 md:grid-cols-2">
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_is_always_available"
                                            @update:checked="(v: boolean) => (formInfo.campaign_is_always_available = v)"
                                        />
                                        <span>Always available</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_ttw_only"
                                            @update:checked="(v: boolean) => (formInfo.campaign_ttw_only = v)"
                                        />
                                        <span>Those Who Thirst only</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_is_omens_mark"
                                            @update:checked="(v: boolean) => (formInfo.campaign_is_omens_mark = v)"
                                        />
                                        <span>Omen's Mark (mandatory attach)</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_is_unique"
                                            @update:checked="(v: boolean) => (formInfo.campaign_is_unique = v)"
                                        />
                                        <span>Unique (Vengeful Vow style)</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_leader_only"
                                            @update:checked="(v: boolean) => (formInfo.campaign_leader_only = v)"
                                        />
                                        <span>Leader only</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_non_unique_only"
                                            @update:checked="(v: boolean) => (formInfo.campaign_non_unique_only = v)"
                                        />
                                        <span>Non-unique only</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_annihilate_after_game"
                                            @update:checked="(v: boolean) => (formInfo.campaign_annihilate_after_game = v)"
                                        />
                                        <span>Annihilate after game (Loot Their Stash)</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_is_red_joker_entry"
                                            @update:checked="(v: boolean) => (formInfo.campaign_is_red_joker_entry = v)"
                                        />
                                        <span>Red Joker entry (TTW sub-table)</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Injury-specific fields -->
                            <div v-if="formInfo.campaign_upgrade_kind === 'injury'" class="space-y-3 border-t pt-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="campaign_flip_value_injury">Flip Value (1–13)</Label>
                                        <Input
                                            id="campaign_flip_value_injury"
                                            type="number"
                                            min="1"
                                            max="13"
                                            v-model.number="formInfo.campaign_flip_value"
                                        />
                                        <p class="text-[11px] text-muted-foreground">Leave blank for joker entries.</p>
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="campaign_suit_pool">Suit Pool</Label>
                                        <Select id="campaign_suit_pool" v-model="formInfo.campaign_suit_pool">
                                            <SelectTrigger>
                                                <SelectValue placeholder="pc / te / joker" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="pc">PC (red half)</SelectItem>
                                                <SelectItem value="te">TE (black half)</SelectItem>
                                                <SelectItem value="black_joker">Black Joker</SelectItem>
                                                <SelectItem value="red_joker">Red Joker</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div class="grid gap-2 md:grid-cols-2">
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_is_traitor"
                                            @update:checked="(v: boolean) => (formInfo.campaign_is_traitor = v)"
                                        />
                                        <span>Traitor (cross-crew transfer)</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_is_close_call"
                                            @update:checked="(v: boolean) => (formInfo.campaign_is_close_call = v)"
                                        />
                                        <span>Close Call (Lucky Miss flow)</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_annihilates_model"
                                            @update:checked="(v: boolean) => (formInfo.campaign_annihilates_model = v)"
                                        />
                                        <span>Annihilates the model (Killed Off)</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_reflip_if_no_triggers"
                                            @update:checked="(v: boolean) => (formInfo.campaign_reflip_if_no_triggers = v)"
                                        />
                                        <span>Reflip if no triggers (Permanent Hex)</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_reflip_if_master_or_totem"
                                            @update:checked="(v: boolean) => (formInfo.campaign_reflip_if_master_or_totem = v)"
                                        />
                                        <span>Reflip if master or totem (Headstrong / Traitor)</span>
                                    </label>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-end gap-2 px-6 pb-6">
                <Button @click="router.get(route('admin.upgrades.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
