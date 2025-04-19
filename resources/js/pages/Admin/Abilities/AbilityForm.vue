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
    ability: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    defensive_ability_types: {
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
    suits: null,
    defensive_ability_type: null,
    costs_stone: false,
    description: null,
    characters: [],
});

const submit = () => {
    router.post(props.ability ? route("admin.abilities.update", props.ability.slug) : route("admin.abilities.store"),
        formInfo.value
    );
};

onMounted(() => {
    formInfo.value.name = props.ability?.name ?? null;
    formInfo.value.costs_stone = props.ability?.costs_stone ?? false;
    formInfo.value.defensive_ability_type = props.ability?.defensive_ability_type ?? null;
    formInfo.value.suits = props.ability?.suits ?? null;
    formInfo.value.description = props.ability?.description ?? null;

    props.ability?.characters.forEach((character) => {
        formInfo.value.characters.push(character.display_name);
    });
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Ability</CardTitle>
                <CardDescription>Create and Edit Ability Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" autofocus v-model="formInfo.name" placeholder="Ability Name" />
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
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="suits">Required Suits</Label>
                                    <Input id="suits" v-model="formInfo.suits" placeholder="Required Suits" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
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
                                <Label for="description">Ability Text</Label>
                                <Textarea id="description" v-model="formInfo.description" placeholder="Type the ability text here." />
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
                <Button @click="router.get(route('admin.abilities.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
