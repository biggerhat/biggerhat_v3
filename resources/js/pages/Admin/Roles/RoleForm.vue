<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { onMounted } from 'vue';

const props = defineProps({
    role: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    checked_permissions: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    permissions: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
});

const form = useForm({
    name: '',
    permissions: [] as string[],
});

const togglePermission = (permission: string) => {
    for (let i = 0; i < form.permissions.length; i++) {
        if (form.permissions[i] === permission) {
            form.permissions.splice(i, 1);
            return;
        }
    }

    form.permissions.push(permission);
};

onMounted(() => {
    form.name = props.role?.name ?? '';
    if (props.role) {
        props.role.permissions.forEach((permission: any) => {
            togglePermission(permission.name);
        });
    }
});

const submit = () => {
    if (props.role) {
        form.post(route('admin.roles.update', { role: props.role.id }));
    } else {
        form.post(route('admin.roles.store'));
    }
};
</script>

<template>
    <Head title="Role Information" />

    <div class="mx-4 mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Role Form</CardTitle>
                <CardDescription>Create and Edit Role Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit">
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Role</Label>
                            <Input id="name" type="text" required autofocus :tabindex="1" autocomplete="name" v-model="form.name" placeholder="Role" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid w-full md:grid-cols-4">
                            <div v-for="(permissions, key) in props.permissions" :key="key" class="mb-4">
                                {{ key }}
                                <hr />
                                <div v-for="permission in permissions" :key="permission.value" class="flex items-center space-x-2 space-y-2 py-2">
                                    <Checkbox
                                        :id="permission.value"
                                        class="my-auto inline-block"
                                        :checked="form.permissions.includes(permission.value)"
                                        @update:checked="togglePermission(permission.value)"
                                    />
                                    <label
                                        class="my-auto text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                        :for="permission.value"
                                    >
                                        {{ permission.name }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <Button type="submit" class="mt-2 w-full" tabindex="5" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            <span v-if="props.role">Update Role</span>
                            <span v-else>Create Role</span>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
