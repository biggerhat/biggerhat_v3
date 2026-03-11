<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, KeyRound, LoaderCircle } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

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
    form.post(route('admin.users.update', { user: props.user.slug }));
};

// ─── Password Reset Link ───
const page = usePage<SharedData>();
const resetLink = computed(() => (page.props.flash as any)?.reset_link ?? null);
const generatingResetLink = ref(false);
const resetLinkCopied = ref(false);

const generateResetLink = () => {
    generatingResetLink.value = true;
    router.post(route('admin.users.password_reset_link', { user: props.user.slug }), {}, {
        preserveScroll: true,
        onFinish: () => (generatingResetLink.value = false),
    });
};

const copyResetLink = async () => {
    if (!resetLink.value) return;
    await navigator.clipboard.writeText(resetLink.value);
    resetLinkCopied.value = true;
    setTimeout(() => (resetLinkCopied.value = false), 2000);
};
</script>

<template>
    <Head title="User Information" />

    <div class="mx-4 mt-6 space-y-6">
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
                                    :checked="form.roles.includes(role.name)"
                                    @update:checked="toggleRole(role.name)"
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

        <!-- Password Reset Link -->
        <Card>
            <CardHeader>
                <CardTitle>Password Reset</CardTitle>
                <CardDescription>Generate a one-time password reset link for this user</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <Button variant="outline" class="gap-2" :disabled="generatingResetLink" @click="generateResetLink">
                    <LoaderCircle v-if="generatingResetLink" class="size-4 animate-spin" />
                    <KeyRound v-else class="size-4" />
                    Generate Reset Link
                </Button>

                <div v-if="resetLink" class="space-y-2">
                    <Label>Reset Link</Label>
                    <p class="text-xs text-muted-foreground">This link expires in 60 minutes and can only be used once.</p>
                    <div class="flex gap-2">
                        <Input :model-value="resetLink" readonly class="flex-1 font-mono text-xs" />
                        <Button variant="outline" size="icon" @click="copyResetLink">
                            <Check v-if="resetLinkCopied" class="size-4" />
                            <Copy v-else class="size-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
