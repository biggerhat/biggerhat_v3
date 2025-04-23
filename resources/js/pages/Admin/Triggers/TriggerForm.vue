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
    trigger: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    suits: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
});

const formInfo = ref({
    name: null,
    suits: null,
    costs_stone: false,
    description: null,
});

const submit = () => {
    router.post(props.trigger ? route("admin.triggers.update", props.trigger.slug) : route("admin.triggers.store"),
        formInfo.value
    );
};

onMounted(() => {
    formInfo.value.name = props.trigger?.name ?? null;
    formInfo.value.costs_stone = props.trigger?.costs_stone ?? false;
    formInfo.value.suits = props.trigger?.suits ?? null;
    formInfo.value.description = props.trigger?.description ?? null;
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Trigger</CardTitle>
                <CardDescription>Create and Edit Trigger Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" autofocus v-model="formInfo.name" placeholder="Trigger Name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="suits">Required Suits</Label>
                                    <Input id="suits" v-model="formInfo.suits" placeholder="Required Suits" />
                                </div>
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
                                <Label for="description">Trigger Text</Label>
                                <Textarea id="description" v-model="formInfo.description" placeholder="Type the trigger text here." />
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.triggers.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
