<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { onMounted } from 'vue';

const props = defineProps({
    user: {
        type: [Object, Array],
        required: true,
    },
    checked_roles: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    roles: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

const form = useForm({
    roles: [] as string[],
});

const toggleRole = (roleName: string) => {
    for (let i = 0; i < form.roles.length; i++) {
        if (form.roles[i] === roleName) {
            form.roles.splice(i, 1);
            return;
        }
    }

    form.roles.push(roleName);
};

onMounted(() => {
    if (props.checked_roles) {
        (props.checked_roles as string[]).forEach((roleName: string) => {
            form.roles.push(roleName);
        });
    }
});

const submit = () => {
    form.post(route('admin.users.update', { user: props.user.id }));
};
</script>

<template>
    <Head title="User Information" />

    <div class="mx-4 mt-6">
        <Card>
            <CardHeader>
                <CardTitle>User Form</CardTitle>
                <CardDescription>Edit User Role Assignments</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent="submit">
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label>Name</Label>
                            <p class="text-sm text-muted-foreground">{{ props.user.name }}</p>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label>Email</Label>
                            <p class="text-sm text-muted-foreground">{{ props.user.email }}</p>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label>Roles</Label>
                            <InputError :message="form.errors.roles" />
                            <div v-for="role in props.roles" :key="role.id" class="flex items-center space-x-2 py-2">
                                <Checkbox
                                    :id="'role-' + role.id"
                                    class="my-auto inline-block"
                                    :default-value="props.checked_roles.includes(role.name)"
                                    @update:modelValue="toggleRole(role.name)"
                                />
                                <label
                                    class="my-auto text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                                    :for="'role-' + role.id"
                                >
                                    {{ role.name }}
                                </label>
                            </div>
                        </div>

                        <Button type="submit" class="mt-2 w-full" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            Update User
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
