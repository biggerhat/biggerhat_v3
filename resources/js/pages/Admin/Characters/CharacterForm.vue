<script setup lang='ts'>
import { computed, ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch'
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import {
    NumberField,
    NumberFieldContent,
    NumberFieldDecrement,
    NumberFieldIncrement,
    NumberFieldInput,
} from '@/components/ui/number-field'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import CustomMultiselect from "@/components/CustomMultiselect.vue";
import { Textarea } from '@/components/ui/textarea'

const props = defineProps({
    character: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    factions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    totems: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    crew_upgrades: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    stations: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    base_sizes: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    keywords: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    characteristics: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    miniatures: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    actions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    abilities: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    markers: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    tokens: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    },
    suits: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    }
});

const formInfo = ref({
    name: null,
    title: null,
    nicknames: null,
    station: null,
    crew_upgrade: null,
    totem: null,
    faction: null,
    keywords: [],
    characteristics: [],
    // miniatures: [],
    signature_actions: [],
    actions: [],
    abilities: [],
    markers: [],
    tokens: [],
    cost: null,
    health: null,
    size: null,
    base: null,
    defense: null,
    defense_suit: null,
    willpower: null,
    willpower_suit: null,
    speed: null,
    summon_target_number: null,
    count: 1,
    generates_stone: true,
    is_unhirable: false,
    is_beta: false,
    is_hidden: false,
});

const submit = () => {
    router.post(props.character ? route("admin.characters.update", props.character.slug) : route("admin.characters.store"),
        formInfo.value
    );
};

onMounted(() => {
    formInfo.value.name = props.character?.name ?? null;
    formInfo.value.title = props.character?.title ?? null;
    formInfo.value.nicknames = props.character?.nicknames ?? null;
    formInfo.value.station = props.character?.station ?? null;
    formInfo.value.totem = props.character?.has_totem_id ? props.character?.totem.slug : null;
    formInfo.value.crew_upgrade = props.character?.crew_upgrade ? props.character?.crew_upgrade.slug : null;
    formInfo.value.faction = props.character?.faction ?? null;
    formInfo.value.cost = props.character?.cost ?? null;
    formInfo.value.health = props.character?.health ?? null;
    formInfo.value.size = props.character?.size ?? null;
    formInfo.value.base = props.character?.base ?? null;
    formInfo.value.defense = props.character?.defense ?? null;
    formInfo.value.defense_suit = props.character?.defense_suit ?? null;
    formInfo.value.willpower = props.character?.willpower ?? null;
    formInfo.value.willpower_suit = props.character?.willpower_suit ?? null;
    formInfo.value.speed = props.character?.speed ?? null;
    formInfo.value.count = props.character?.count ?? 1;
    formInfo.value.summon_target_number = props.character?.summon_target_number ?? null;
    formInfo.value.generates_stone = props.character?.generates_stone ?? true;
    formInfo.value.is_unhirable = props.character?.is_unhirable ?? false;
    formInfo.value.is_beta = props.character?.is_beta ?? false;
    formInfo.value.is_hidden = props.character?.is_hidden ?? false;

    props.character?.keywords.forEach((keyword) => {
        formInfo.value.keywords.push(keyword.name);
    });

    props.character?.characteristics.forEach((characteristic) => {
        formInfo.value.characteristics.push(characteristic.name);
    });

    // props.character?.miniatures.forEach((miniature) => {
    //     formInfo.value.miniatures.push(miniature.display_name);
    // });

    props.character?.abilities.forEach((ability) => {
        formInfo.value.abilities.push(ability.name);
    });

    props.character?.actions.forEach((action) => {
        if (action.pivot.is_signature_action) {
            formInfo.value.signature_actions.push(action.name);
        } else {
            formInfo.value.actions.push(action.name);
        }
    });

    props.character?.markers.forEach((marker) => {
        formInfo.value.markers.push(marker.name);
    });

    props.character?.tokens.forEach((token) => {
        formInfo.value.tokens.push(token.name);
    });
});
</script>

<template>
    <div class="container mx-auto mt-6 mb-6">
        <Card>
            <CardHeader>
                <CardTitle>Character</CardTitle>
                <CardDescription>Create and Edit Character Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Character Name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="formInfo.title" placeholder="Character Title" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="nicknames">Nicknames (For Bot Lookup, Not Previous Edition Names)</Label>
                            <Input id="nicknames" v-model="formInfo.nicknames" placeholder="Nicknames" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="faction">Faction</Label>
                            <Select id="faction" v-model="formInfo.faction">
                                <SelectTrigger>
                                    <SelectValue placeholder="Character Faction" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="faction in props.factions" :value="faction.value" :key="faction.value">
                                        {{ faction.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="station">Station</Label>
                            <Select id="station" v-model="formInfo.station">
                                <SelectTrigger>
                                    <SelectValue placeholder="Character Station" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="station in props.stations" :value="station.value" :key="station.value">
                                        {{ station.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col space-y-1.5" v-if="formInfo.station === 'master'">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="totem">Master Totem</Label>
                                    <Select id="totem" v-model="formInfo.totem">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Master Totem" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="totem in props.totems" :value="totem.value" :key="totem.value">
                                                {{ totem.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="crew_upgrade">Crew Upgrade</Label>
                                    <Select id="crew_upgrade" v-model="formInfo.crew_upgrade">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Crew Upgrade" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="upgrade in props.crew_upgrades" :value="upgrade.value" :key="upgrade.value">
                                                {{ upgrade.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <CustomMultiselect v-model="formInfo.keywords" comboTitle="Select Keywords" :choiceOptions="props.keywords" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <CustomMultiselect v-model="formInfo.characteristics" comboTitle="Select Characteristics" :choiceOptions="props.characteristics" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="base">Base Size</Label>
                            <Select id="base" v-model="formInfo.base">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Base Size" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="base in props.base_sizes" :value="base.value" :key="base.value">
                                        {{ base.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="cost">Cost</Label>
                                    <Input id="cost" v-model="formInfo.cost" type="number" placeholder="Character Cost" />
                                </div>
                                <div class="flex flex-col">
                                    <NumberField id="health" v-model="formInfo.health" :default-value="0" :min="0">
                                        <Label for="health">Health</Label>
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                                <div class="flex flex-col">
                                    <NumberField id="size" v-model="formInfo.size" :default-value="0" :min="0">
                                        <Label for="size">Size</Label>
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                                <div class="flex flex-col">
                                    <NumberField id="speed" v-model="formInfo.speed" :default-value="0" :min="0">
                                        <Label for="speed">Speed</Label>
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                                <div class="flex flex-col">
                                    <NumberField id="count" v-model="formInfo.count" :default-value="1" :min="1">
                                        <Label for="count">Model Count</Label>
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="summon_target_number">Summon Target Number</Label>
                                    <Input id="summon_target_number" v-model="formInfo.summon_target_number" type="number" placeholder="Summon Target Number" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <NumberField id="defense" v-model="formInfo.defense" :default-value="0" :min="0">
                                        <Label for="defense">Defense</Label>
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="defense_suit">Defense Suit</Label>
                                    <Select id="defense_suit" v-model="formInfo.defense_suit">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Suit" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="suit in props.suits" :value="suit.value" :key="suit.value">
                                                {{ suit.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <NumberField id="willpower" v-model="formInfo.willpower" :default-value="0" :min="0">
                                        <Label for="willpower">Willpower</Label>
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="willpower_suit">Willpower Suit</Label>
                                    <Select id="willpower_suit" v-model="formInfo.willpower_suit">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Suit" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="suit in props.suits" :value="suit.value" :key="suit.value">
                                                {{ suit.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
<!--                        <div class="flex flex-col space-y-1.5">-->
<!--                            <CustomMultiselect v-model="formInfo.miniatures" comboTitle="Select Miniatures" :choiceOptions="props.miniatures" />-->
<!--                        </div>-->
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model="formInfo.abilities" comboTitle="Select Abilities" :choiceOptions="props.abilities" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model="formInfo.actions" comboTitle="Select Actions" :choiceOptions="props.actions" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model=formInfo.signature_actions comboTitle="Select Signature Actions" :choice-options="props.actions" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model="formInfo.markers" comboTitle="Select Markers" :choiceOptions="props.markers" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <CustomMultiselect v-model="formInfo.tokens" comboTitle="Select Tokens" :choiceOptions="props.tokens" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5 mb-6">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-4">
                                <div class="flex flex-col space-y-1.5 items-center">
                                    <div class="flex items-center space-x-2">
                                        <Switch id="generates_stone" v-model="formInfo.generates_stone" />
                                        <Label for="generates_stone">Generates Stone</Label>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-1.5 items-center">
                                    <div class="flex items-center space-x-2">
                                        <Switch id="is_unhirable" v-model="formInfo.is_unhirable" />
                                        <Label for="is_unhirable">Unhirable</Label>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-1.5 items-center">
                                    <div class="flex items-center space-x-2">
                                        <Switch id="is_beta" v-model="formInfo.is_beta" />
                                        <Label for="is_beta">Beta</Label>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-1.5 items-center">
                                    <div class="flex items-center space-x-2">
                                        <Switch id="is_hidden" v-model="formInfo.is_hidden" />
                                        <Label for="is_hidden">Hidden From Public</Label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.characters.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
