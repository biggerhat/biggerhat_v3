<script setup lang="ts">
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface SelectOption {
    name: string;
    value: string;
}

const props = defineProps({
    transmission: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    channels: {
        type: Array as () => SelectOption[],
        required: false,
        default() {
            return [];
        },
    },
    transmission_types: {
        type: Array as () => SelectOption[],
        required: false,
        default() {
            return [];
        },
    },
    content_types: {
        type: Array as () => SelectOption[],
        required: false,
        default() {
            return [];
        },
    },
    factions: {
        type: Array as () => SelectOption[],
        required: false,
        default() {
            return [];
        },
    },
    characters: {
        type: Array as () => SelectOption[],
        required: false,
        default() {
            return [];
        },
    },
    keywords: {
        type: Array as () => SelectOption[],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    title: null as string | null,
    description: null as string | null,
    url: null as string | null,
    channel_id: null as string | null,
    transmission_type: null as string | null,
    content_type: null as string | null,
    factions: [] as string[],
    release_date: null as string | null,
    characters: [] as string[],
    keywords: [] as string[],
});

const toggleFaction = (value: string, checked: boolean) => {
    if (checked) {
        formInfo.value.factions.push(value);
    } else {
        formInfo.value.factions = formInfo.value.factions.filter((f) => f !== value);
    }
};

const submit = () => {
    router.post(
        props.transmission ? route('admin.transmissions.update', props.transmission.slug) : route('admin.transmissions.store'),
        formInfo.value,
    );
};

onMounted(() => {
    if (props.transmission) {
        formInfo.value.title = props.transmission.title;
        formInfo.value.description = props.transmission.description;
        formInfo.value.url = props.transmission.url;
        formInfo.value.channel_id = String(props.transmission.channel_id ?? props.transmission.channel?.id);
        formInfo.value.transmission_type = props.transmission.transmission_type;
        formInfo.value.content_type = props.transmission.content_type;
        formInfo.value.factions = props.transmission.factions ?? [];
        formInfo.value.release_date = props.transmission.release_date?.split('T')[0] ?? null;
        formInfo.value.characters = props.transmission.characters?.map((c: { slug: string }) => c.slug) ?? [];
        formInfo.value.keywords = props.transmission.keywords?.map((k: { slug: string }) => k.slug) ?? [];
    }
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Transmission</CardTitle>
                <CardDescription>Create and Edit Transmission Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="title">Title</Label>
                            <Input id="title" v-model="formInfo.title" placeholder="Transmission Title" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="url">URL</Label>
                            <Input id="url" v-model="formInfo.url" placeholder="https://..." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="formInfo.description" placeholder="Transmission description..." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="channel_id">Channel</Label>
                            <Select v-model="formInfo.channel_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a channel" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="channel in channels" :key="channel.value" :value="channel.value">
                                        {{ channel.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="transmission_type">Platform</Label>
                                <Select v-model="formInfo.transmission_type">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select platform" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="tt in transmission_types" :key="tt.value" :value="tt.value">
                                            {{ tt.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="flex flex-col space-y-1.5">
                                <Label for="content_type">Content Type</Label>
                                <Select v-model="formInfo.content_type">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select content type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="ct in content_types" :key="ct.value" :value="ct.value">
                                            {{ ct.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="release_date">Release Date</Label>
                            <Input id="release_date" type="date" v-model="formInfo.release_date" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label>Factions</Label>
                            <div class="flex flex-wrap gap-3">
                                <label v-for="faction in factions" :key="faction.value" class="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        :checked="formInfo.factions.includes(faction.value)"
                                        @update:checked="toggleFaction(faction.value, $event as boolean)"
                                    />
                                    {{ faction.name }}
                                </label>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label>Characters</Label>
                            <SearchableMultiselect v-model="formInfo.characters" :options="characters" placeholder="Search characters..." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label>Keywords</Label>
                            <SearchableMultiselect v-model="formInfo.keywords" :options="keywords" placeholder="Search keywords..." />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.transmissions.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
