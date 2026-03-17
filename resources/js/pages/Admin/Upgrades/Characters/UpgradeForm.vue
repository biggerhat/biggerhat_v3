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
});

const formInfo = ref({
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
});

const submit = () => {
    router.post(props.upgrade ? route('admin.upgrades.update', props.upgrade.slug) : route('admin.upgrades.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.upgrade?.name ?? null;
    formInfo.value.faction = props.upgrade?.faction ?? null;
    formInfo.value.description = props.upgrade?.description ?? null;
    formInfo.value.power_bar_count = props.upgrade?.power_bar_count ?? null;
    formInfo.value.plentiful = props.upgrade?.plentiful ?? 1;
    formInfo.value.limitations = props.upgrade?.limitations ?? null;
    formInfo.value.type = props.upgrade?.type ?? null;

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
