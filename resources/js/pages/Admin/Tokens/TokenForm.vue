<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    token: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    all_characters: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    all_upgrades: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        },
    },
    removal_timing_options: {
        type: Array as () => Array<{ name: string; value: string }>,
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    name: null,
    description: null,
    removal_timing: null as string | null,
    is_general: false,
    characters: [] as string[],
    upgrades: [] as string[],
});

const submit = () => {
    router.post(props.token ? route('admin.tokens.update', props.token.slug) : route('admin.tokens.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.token?.name ?? null;
    formInfo.value.description = props.token?.description ?? null;
    formInfo.value.removal_timing = props.token?.removal_timing ?? null;
    formInfo.value.is_general = props.token?.is_general ?? false;

    props.token?.characters?.forEach((c: any) => {
        formInfo.value.characters.push(c.slug);
    });

    props.token?.upgrades?.forEach((u: any) => {
        formInfo.value.upgrades.push(u.slug);
    });
});
</script>

<template>
    <Head title="Tokens - Admin" />
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Token</CardTitle>
                <CardDescription>Create and Edit Token Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Token Name" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="formInfo.description" placeholder="Type the token description here." />
                            <InputError :message="usePage().props.errors.description" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="removal_timing">Auto-removal timing</Label>
                            <select
                                id="removal_timing"
                                v-model="formInfo.removal_timing"
                                class="h-9 rounded-md border border-input bg-background px-2 text-sm"
                            >
                                <option :value="null">Persists (manual removal)</option>
                                <option v-for="opt in props.removal_timing_options" :key="opt.value" :value="opt.value">{{ opt.name }}</option>
                            </select>
                            <InputError :message="usePage().props.errors.removal_timing" />
                        </div>
                        <div class="flex items-center gap-2">
                            <input id="is_general" v-model="formInfo.is_general" type="checkbox" class="size-4 rounded border-input" />
                            <Label for="is_general">General token — show in every crew's tracker references</Label>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <SearchableMultiselect v-model="formInfo.characters" placeholder="Linked Characters" :options="props.all_characters" />
                            <InputError :message="usePage().props.errors.characters" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <SearchableMultiselect v-model="formInfo.upgrades" placeholder="Linked Upgrades" :options="props.all_upgrades" />
                            <InputError :message="usePage().props.errors.upgrades" />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.tokens.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
