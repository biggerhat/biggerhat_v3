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
    miniature: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
    version_types: {
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
    character_id: null,
    front_image: null,
    back_image: null,
    combination_image: null,
    version: null,
});

const submit = () => {
    const formData = new FormData();
    router.post(props.miniature ? route("admin.miniatures.update", props.miniature.id) : route("admin.miniatures.store"),
        formInfo.value
    );
};

onMounted(() => {
    formInfo.value.name = props.miniature?.name ?? null;
    formInfo.value.title = props.miniature?.title ?? null;
    formInfo.value.character_id = props.miniature?.character_id ?? null;
    formInfo.value.version = props.miniature?.version ?? null;
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Miniature</CardTitle>
                <CardDescription>Create and Edit Miniature Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name (Optional)</Label>
                            <span class="text-xs text-red-600">Leave Blank If Normal Sculpt Or No Alternate Name</span>
                            <Input id="name" v-model="formInfo.name" autofocus placeholder="Miniature Name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="title">Title (Optional)</Label>
                            <span class="text-xs text-red-600">Leave Blank If Normal Sculpt Or No Alternate Title</span>
                            <Input id="title" v-model="formInfo.title" autofocus placeholder="Miniature Title" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="character">Character</Label>
                                    <Select id="character" v-model="formInfo.character_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Character" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="character in props.characters" :value="character.value" :key="character.value">
                                                {{ character.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="sculpt_version">Sculpt Version</Label>
                                    <Select id="sculpt_version" v-model="formInfo.version">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Sculpt Version" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="version in props.version_types" :value="version.value" :key="version.value">
                                                {{ version.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col w-full max-w-sm items-center gap-1.5 space-y-1.5">
                                    <Label for="front_image">Front of Card Image</Label>
                                    <Input id="front_image" type="file" accept=".jpeg, .jpg" @input="formInfo.front_image = $event.target.files[0]" />
                                </div>
                                <div class="flex flex-col w-full max-w-sm items-center gap-1.5 space-y-1.5">
                                    <Label for="back_image">Back of Card Image</Label>
                                    <Input id="back_image" type="file" accept=".jpeg, .jpg" @input="formInfo.back_image = $event.target.files[0]" />
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.miniatures.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
