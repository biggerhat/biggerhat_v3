<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    ability: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    defensive_ability_types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    suits: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    characters: {
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
    suits: null,
    defensive_ability_type: null,
    costs_stone: false,
    description: null,
    characters: [],
    // Campaign-only fields — null/false on Standard abilities. Surfaced
    // conditionally in the template when game_mode_type === 'campaign'.
    campaign_flip_value: null as number | null,
    campaign_is_always_available: false,
    campaign_joker_freechoice: false,
    is_crew_card_effect: false,
    requires_token_choice: false,
    requires_marker_choice: false,
    requires_upgrade_type_choice: false,
});

const submit = () => {
    router.post(props.ability ? route('admin.abilities.update', props.ability.slug) : route('admin.abilities.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.game_mode_type = props.ability?.game_mode_type ?? 'standard';
    formInfo.value.name = props.ability?.name ?? null;
    formInfo.value.costs_stone = props.ability?.costs_stone ?? false;
    formInfo.value.defensive_ability_type = props.ability?.defensive_ability_type ?? null;
    formInfo.value.suits = props.ability?.suits ?? null;
    formInfo.value.description = props.ability?.description ?? null;

    // Campaign-only fields — populate from the ability if present.
    formInfo.value.campaign_flip_value = props.ability?.campaign_flip_value ?? null;
    formInfo.value.campaign_is_always_available = props.ability?.campaign_is_always_available ?? false;
    formInfo.value.campaign_joker_freechoice = props.ability?.campaign_joker_freechoice ?? false;
    formInfo.value.is_crew_card_effect = props.ability?.is_crew_card_effect ?? false;
    formInfo.value.requires_token_choice = props.ability?.requires_token_choice ?? false;
    formInfo.value.requires_marker_choice = props.ability?.requires_marker_choice ?? false;
    formInfo.value.requires_upgrade_type_choice = props.ability?.requires_upgrade_type_choice ?? false;

    props.ability?.characters.forEach((character) => {
        formInfo.value.characters.push(character.display_name);
    });
});
</script>

<template>
    <Head title="Ability Form" />
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Ability</CardTitle>
                <CardDescription>Create and Edit Ability Information</CardDescription>
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
                            <Input id="name" autofocus v-model="formInfo.name" placeholder="Ability Name" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="type">Defensive Ability Type</Label>
                                    <Select id="type" v-model="formInfo.defensive_ability_type">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Defensive Ability Type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="type in props.defensive_ability_types" :value="type.value" :key="type.value">
                                                {{ type.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError :message="usePage().props.errors.defensive_ability_type" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="suits">Required Suits</Label>
                                    <Input id="suits" v-model="formInfo.suits" placeholder="Required Suits" />
                                    <InputError :message="usePage().props.errors.suits" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                                <div class="flex flex-col items-center space-y-1.5">
                                    <div class="flex items-center space-x-2">
                                        <Switch id="costs_stone" v-model="formInfo.costs_stone" />
                                        <Label for="costs_stone">Costs A Stone</Label>
                                    </div>
                                    <InputError :message="usePage().props.errors.costs_stone" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="description">Ability Text</Label>
                                <Textarea id="description" v-model="formInfo.description" placeholder="Type the ability text here." />
                                <InputError :message="usePage().props.errors.description" />
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="characters">Characters</Label>
                                <SearchableMultiselect
                                    v-model="formInfo.characters"
                                    placeholder="Select Characters"
                                    :options="props.characters"
                                    option-value="name"
                                />
                                <InputError :message="usePage().props.errors.characters" />
                            </div>
                        </div>

                        <!-- Campaign-only fields. Hidden by default; renders
                             only when Game Mode is Campaign. The data still
                             posts whether the fieldset is visible or not — the
                             server zeroes out campaign columns when mode flips
                             back to standard via the FormRequest validator. -->
                        <fieldset
                            v-if="formInfo.game_mode_type === 'campaign'"
                            class="space-y-3 rounded-md border border-primary/30 bg-primary/5 p-3"
                        >
                            <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Campaign Mode</legend>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="campaign_flip_value">Flip Value (1–13)</Label>
                                    <Input id="campaign_flip_value" type="number" min="1" max="13" v-model.number="formInfo.campaign_flip_value" />
                                    <p class="text-[11px] text-muted-foreground">
                                        Aftermath XP-advancement gating. Leave blank for "Always Available" abilities.
                                    </p>
                                </div>
                                <div class="flex flex-col gap-2 pt-5">
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_is_always_available"
                                            @update:checked="(v: boolean) => (formInfo.campaign_is_always_available = v)"
                                        />
                                        <span>Always available</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.campaign_joker_freechoice"
                                            @update:checked="(v: boolean) => (formInfo.campaign_joker_freechoice = v)"
                                        />
                                        <span>Joker "Choose freely"</span>
                                    </label>
                                </div>
                            </div>
                            <div class="flex flex-col gap-2 border-t pt-3">
                                <label class="flex items-center gap-2 text-sm font-medium">
                                    <Checkbox
                                        :checked="formInfo.is_crew_card_effect"
                                        @update:checked="(v: boolean) => (formInfo.is_crew_card_effect = v)"
                                    />
                                    <span>This ability is a Crew Card Effect</span>
                                </label>
                                <div v-if="formInfo.is_crew_card_effect" class="ml-6 flex flex-col gap-1.5">
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.requires_token_choice"
                                            @update:checked="(v: boolean) => (formInfo.requires_token_choice = v)"
                                        />
                                        <span>Requires Token choice on activation</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.requires_marker_choice"
                                            @update:checked="(v: boolean) => (formInfo.requires_marker_choice = v)"
                                        />
                                        <span>Requires Marker choice on activation</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :checked="formInfo.requires_upgrade_type_choice"
                                            @update:checked="(v: boolean) => (formInfo.requires_upgrade_type_choice = v)"
                                        />
                                        <span>Requires Upgrade-Type choice on activation</span>
                                    </label>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.abilities.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
