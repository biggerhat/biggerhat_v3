<script setup lang="ts">
import CustomMultiselect from '@/components/CustomMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    lore: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    lore_media: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    media_types: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    characters: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    name: null as string | null,
    lore_media_id: null as number | string | null,
    characters: [] as string[],
    new_media_name: null as string | null,
    new_media_type: null as string | null,
    new_media_link: null as string | null,
});

const showNewMedia = ref(false);

const submit = () => {
    router.post(props.lore ? route('admin.lores.update', props.lore.slug) : route('admin.lores.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.lore?.name ?? null;
    formInfo.value.lore_media_id = props.lore?.lore_media_id ?? null;

    props.lore?.characters?.forEach((character: any) => {
        formInfo.value.characters.push(character.display_name);
    });
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Lore</CardTitle>
                <CardDescription>Create and Edit Lore Entries</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Story Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Lore Story Name" />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label for="lore_media_id">Media Source</Label>
                            <Select id="lore_media_id" v-model="formInfo.lore_media_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select Media Source" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="media in props.lore_media" :value="media.value" :key="media.value">
                                        {{ media.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Button type="button" variant="outline" size="sm" @click="showNewMedia = !showNewMedia">
                                {{ showNewMedia ? 'Cancel New Media' : '+ Create New Media Source' }}
                            </Button>
                        </div>

                        <template v-if="showNewMedia">
                            <Separator label="New Media Source" />
                            <div class="rounded-md border bg-muted/30 p-4">
                                <div class="grid w-full items-center gap-4">
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="new_media_name">Media Name</Label>
                                        <Input id="new_media_name" v-model="formInfo.new_media_name" placeholder="Media Name" />
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="new_media_type">Media Type</Label>
                                        <Select id="new_media_type" v-model="formInfo.new_media_type">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Media Type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="type in props.media_types" :value="type.value" :key="type.value">
                                                    {{ type.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label for="new_media_link">Link (optional)</Label>
                                        <Input id="new_media_link" v-model="formInfo.new_media_link" placeholder="https://..." />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="flex flex-col space-y-1.5">
                            <Label for="characters">Characters</Label>
                            <CustomMultiselect
                                id="characters"
                                v-model="formInfo.characters"
                                comboTitle="Select Characters"
                                :choice-options="props.characters"
                            />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.lores.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
