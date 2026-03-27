<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import TextBar from '@/components/TextBar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
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
    factions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    keywords: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
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
    all_characters: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    characteristics: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    hiring_rules_fields: {
        type: Object,
        required: false,
        default() {
            return null;
        },
    },
});

const formInfo = ref({
    name: null,
    faction: null,
    description: null,
    power_bar_count: null,
    front_image: null,
    back_image: null,
    combination_image: null,
    tokens: [],
    markers: [],
    triggers: [],
    actions: [],
    signature_actions: [],
    abilities: [],
    characters: [],
    keywords: [],
    hiring_rules_type: null,
    alternate_leader: null,
    any_faction: false,
    fixed_crew_keyword: null,
    fixed_cache: null,
    required_characteristic: null,
    required_count: null,
});

const submit = () => {
    router.post(props.upgrade ? route('admin.crews.update', props.upgrade.slug) : route('admin.crews.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.upgrade?.name ?? null;
    formInfo.value.faction = props.upgrade?.faction ?? null;
    formInfo.value.description = props.upgrade?.description ?? null;
    formInfo.value.power_bar_count = props.upgrade?.power_bar_count ?? null;

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
            formInfo.value.signature_actions.push(action.id + ' ' + action.name + ' ' + action.internal_notes);
        } else {
            formInfo.value.actions.push(action.id + ' ' + action.name + ' ' + action.internal_notes);
        }
    });

    props.upgrade?.abilities.forEach((ability) => {
        formInfo.value.abilities.push(ability.name);
    });

    // Decompose hiring_rules from backend
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
    <div class="container mx-auto mt-6 h-full px-2">
        <Card>
            <CardHeader>
                <CardTitle>Crew Card</CardTitle>
                <CardDescription>Create and Edit Crew Card Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
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
                                            <SelectItem v-for="faction in props.factions" :value="faction.value" :key="faction.value">
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
                                        :options="props.all_characters"
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
                                        :options="props.keywords"
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
                                        option-value="name"
                                    />
                                    <InputError :message="usePage().props.errors.actions" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <SearchableMultiselect
                                        v-model="formInfo.signature_actions"
                                        placeholder="Select Signature Actions"
                                        :options="props.actions"
                                        option-value="name"
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
                            </div>
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

                                <!-- Fixed Crew fields -->
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
                                        <input id="any_faction" type="checkbox" v-model="formInfo.any_faction" class="rounded border-gray-300" />
                                        <Label for="any_faction">Any Faction</Label>
                                    </div>
                                </template>

                                <!-- Required Hires fields -->
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
                <Button @click="router.get(route('admin.crews.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
