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

const props = defineProps({
    marker: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    base_sizes: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
});

const formInfo = ref({
    name: null,
    base: null,
    description: null,
});

const submit = () => {
    router.post(props.marker ? route("admin.markers.update", props.marker.slug) : route("admin.markers.store"),
        formInfo.value
    );
};

onMounted(() => {
    formInfo.value.name = props.marker?.name ?? null;
    formInfo.value.description = props.marker?.description ?? null;
    formInfo.value.base = props.marker?.base ?? null;
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Marker</CardTitle>
                <CardDescription>Create and Edit Marker Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Marker Name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="formInfo.description" placeholder="Type the marker description here." />
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
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.markers.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
