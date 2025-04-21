<script setup lang='ts'>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from "@/components/ui/select";
import {
    NumberField,
    NumberFieldContent,
    NumberFieldDecrement,
    NumberFieldIncrement, NumberFieldInput
} from "@/components/ui/number-field";
import {Switch} from "@/components/ui/switch";
import CustomMultiselect from "@/components/CustomMultiselect.vue";

const props = defineProps({
    action: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    action_types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
    range_types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
    suits: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
    resistance_types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
    modifier_types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
    triggers: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    }
});

const formInfo = ref({
    name: null,
    type: null,
    is_signature: false,
    costs_stone: false,
    range: null,
    range_type: null,
    stat: null,
    stat_suits: null,
    stat_modifier: null,
    resisted_by: null,
    target_number: null,
    target_suits: null,
    description: null,
    damage: null,
    triggers: [],
    characters: [],
});

const submit = () => {
    router.post(props.action ? route("admin.actions.update", props.action.slug) : route("admin.actions.store"),
        formInfo.value
    );
};

onMounted(() => {
    formInfo.value.name = props.action?.name ?? null;
    formInfo.value.type = props.action?.type ?? null;
    formInfo.value.is_signature = props.action?.is_signature ?? false;
    formInfo.value.costs_stone = props.action?.costs_stone ?? false;
    formInfo.value.range = props.action?.range ?? null;
    formInfo.value.range_type = props.action?.range_type ?? null;
    formInfo.value.stat = props.action?.stat ?? null;
    formInfo.value.stat_suits = props.action?.stat_suits ?? null;
    formInfo.value.stat_modifier = props.action?.modifier ?? null;
    formInfo.value.resisted_by = props.action?.resisted_by ?? null;
    formInfo.value.target_number = props.action?.target_number ?? null;
    formInfo.value.target_suits = props.action?.target_suits ?? null;
    formInfo.value.damage = props.action?.damage ?? null;
    formInfo.value.description = props.action?.description ?? null;

    props.action?.triggers.forEach((trigger) => {
        formInfo.value.triggers.push(trigger.name);
    });

    props.action?.characters.forEach((character) => {
        formInfo.value.characters.push(character.display_name);
    });
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Action</CardTitle>
                <CardDescription>Create and Edit Action Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Action Name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="type">Action Type</Label>
                            <Select id="type" v-model="formInfo.type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Action Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="type in props.action_types" :value="type.value" :key="type.value">
                                        {{ type.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="range_type">Range Type</Label>
                                    <Select id="range_type" v-model="formInfo.range_type">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Range Type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="range_type in props.range_types" :value="range_type.value" :key="range_type.value">
                                                {{ range_type.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col">
                                    <NumberField id="range" v-model="formInfo.range" :default-value="0" :min="0">
                                        <Label for="range">Range</Label>
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                                <div class="flex flex-col">
                                    <NumberField id="stat" v-model="formInfo.stat" :default-value="0" :min="0">
                                        <Label for="stat">Skill</Label>
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="stat_modifier">Skill Modifier</Label>
                                    <Select id="stat_modifier" v-model="formInfo.stat_modifier">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Skill Modifier" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="modifier in props.modifier_types" :value="modifier.value" :key="modifier.value">
                                                {{ modifier.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="stat_suits">Built In Stats</Label>
                                    <Input id="stat_suits" v-model="formInfo.stat_suits" placeholder="Built In Stats" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="resisted_by">Resisted By</Label>
                                    <Select id="resisted_by" v-model="formInfo.resisted_by">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Resisted By" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="resistance_type in props.resistance_types" :value="resistance_type.value" :key="resistance_type.value">
                                                {{ resistance_type.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <NumberField id="target_number" v-model="formInfo.target_number" :default-value="0" :min="0">
                                        <Label for="target_number">Target Number</Label>
                                        <NumberFieldContent>
                                            <NumberFieldDecrement />
                                            <NumberFieldInput />
                                            <NumberFieldIncrement />
                                        </NumberFieldContent>
                                    </NumberField>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="target_suits">Target Suits</Label>
                                    <Input id="target_suits" v-model="formInfo.target_suits" placeholder="Target Suits" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="damage">Damage</Label>
                                    <Input id="damage" v-model="formInfo.damage" placeholder="Damage" />
                                </div>
<!--                                <div class="flex flex-col space-y-1.5 items-center">-->
<!--                                    <div class="flex items-center space-x-2">-->
<!--                                        <Switch id="is_signature" v-model="formInfo.is_signature" />-->
<!--                                        <Label for="is_signature">Is Signature Action</Label>-->
<!--                                    </div>-->
<!--                                </div>-->
                                <div class="flex flex-col space-y-1.5 items-center">
                                    <div class="flex items-center space-x-2">
                                        <Switch id="costs_stone" v-model="formInfo.costs_stone" />
                                        <Label for="costs_stone">Costs A Stone</Label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="description">Action Text</Label>
                                <Textarea id="description" v-model="formInfo.description" placeholder="Type the action text here." />
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="triggers">Triggers</Label>
                                <CustomMultiselect id="triggers" v-model="formInfo.triggers" comboTitle="Select Triggers" :choiceOptions="props.triggers" />
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="characters">Characters</Label>
                                <CustomMultiselect id="characters" v-model="formInfo.characters" comboTitle="Select Characters" :choiceOptions="props.characters" />
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.actions.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
