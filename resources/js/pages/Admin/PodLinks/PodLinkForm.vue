<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface SelectOption {
    name: string;
    value: string;
}

const props = defineProps({
    pod_link: {
        type: Object,
        required: false,
        default: () => null,
    },
    sources: {
        type: Array as () => SelectOption[],
        required: false,
        default: () => [],
    },
    all_miniatures: {
        type: Array as () => SelectOption[],
        required: false,
        default: () => [],
    },
    all_upgrades: {
        type: Array as () => SelectOption[],
        required: false,
        default: () => [],
    },
    all_keywords: {
        type: Array as () => SelectOption[],
        required: false,
        default: () => [],
    },
    all_factions: {
        type: Array as () => SelectOption[],
        required: false,
        default: () => [],
    },
});

const formInfo = ref({
    name: '' as string,
    source: '' as string,
    url: '' as string,
    miniatures: [] as string[],
    upgrades: [] as string[],
    keywords: [] as string[],
    factions: [] as string[],
});

const submit = () => {
    const url = props.pod_link ? route('admin.pod_links.update', props.pod_link.slug) : route('admin.pod_links.store');
    router.post(url, formInfo.value);
};

onMounted(() => {
    if (props.pod_link) {
        formInfo.value.name = props.pod_link.name ?? '';
        formInfo.value.source = props.pod_link.source ?? '';
        formInfo.value.url = props.pod_link.url ?? '';
        formInfo.value.miniatures = (props.pod_link.miniatures ?? []).map((m: any) => String(m.id));
        formInfo.value.upgrades = (props.pod_link.upgrades ?? []).map((u: any) => u.slug);
        formInfo.value.keywords = (props.pod_link.keywords ?? []).map((k: any) => k.slug);
        formInfo.value.factions = props.pod_link.faction_tags ?? [];
    }
});
</script>

<template>
    <Head title="POD Link - Admin" />
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Print On Demand Link</CardTitle>
                <CardDescription>Create and edit POD product links</CardDescription>
            </CardHeader>
            <CardContent>
                <form @submit.prevent>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Product Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="e.g. Rasputina Core Box" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="source">Source</Label>
                                <Select id="source" v-model="formInfo.source">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select Source" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="s in sources" :key="s.value" :value="s.value">{{ s.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="usePage().props.errors.source" />
                            </div>

                            <div class="flex flex-col space-y-1.5">
                                <Label for="url">URL</Label>
                                <Input id="url" v-model="formInfo.url" type="url" placeholder="https://..." />
                                <InputError :message="usePage().props.errors.url" />
                            </div>
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label>Linked Miniatures</Label>
                            <SearchableMultiselect v-model="formInfo.miniatures" placeholder="Search miniatures..." :options="all_miniatures" />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label>Linked Upgrades</Label>
                            <SearchableMultiselect v-model="formInfo.upgrades" placeholder="Search upgrades..." :options="all_upgrades" />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label>Linked Keywords</Label>
                            <SearchableMultiselect v-model="formInfo.keywords" placeholder="Search keywords..." :options="all_keywords" />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label>Linked Factions</Label>
                            <SearchableMultiselect v-model="formInfo.factions" placeholder="Search factions..." :options="all_factions" />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.pod_links.index'))" variant="outline">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
