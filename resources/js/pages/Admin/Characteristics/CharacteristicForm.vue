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

const props = defineProps({
    characteristic: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    }
});

const formInfo = ref({
    name: null,
});

const submit = () => {
    router.post(props.characteristic ? route("admin.characteristics.update", props.characteristic.slug) : route("admin.characteristics.store"),
        formInfo.value
    );
};

onMounted(() => {
    formInfo.value.name = props.characteristic?.name ?? null;
    formInfo.value.description = props.characteristic?.description ?? null;
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Characteristic</CardTitle>
                <CardDescription>Create and Edit Characteristic Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Characteristic Name" />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.characteristics.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
