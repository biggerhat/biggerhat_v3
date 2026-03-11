<script setup lang="ts">
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface NewMediaEntry {
    name: string | null;
    type: string | null;
    link: string | null;
}

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
    lore_media: [] as string[],
    characters: [] as string[],
    new_media: [] as NewMediaEntry[],
});

const addNewMedia = () => {
    formInfo.value.new_media.push({ name: null, type: null, link: null });
};

const removeNewMedia = (index: number) => {
    formInfo.value.new_media.splice(index, 1);
};

const submit = () => {
    router.post(props.lore ? route('admin.lores.update', props.lore.id) : route('admin.lores.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.lore?.name ?? null;

    props.lore?.media?.forEach((media: any) => {
        formInfo.value.lore_media.push(media.name);
    });

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
                            <Label for="lore_media">Media Sources</Label>
                            <SearchableMultiselect
                                v-model="formInfo.lore_media"
                                placeholder="Select Media Sources"
                                :options="props.lore_media"
                                option-value="name"
                            />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Button type="button" variant="outline" size="sm" @click="addNewMedia"> + Create New Media Source </Button>
                        </div>

                        <template v-if="formInfo.new_media.length > 0">
                            <Separator label="New Media Sources" />
                            <div v-for="(entry, index) in formInfo.new_media" :key="index" class="rounded-md border bg-muted/30 p-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-sm font-medium">New Media #{{ index + 1 }}</span>
                                    <Button type="button" variant="ghost" size="sm" @click="removeNewMedia(index)">
                                        <Trash2 class="h-4 w-4 text-destructive" />
                                    </Button>
                                </div>
                                <div class="grid w-full items-center gap-4">
                                    <div class="flex flex-col space-y-1.5">
                                        <Label :for="`new_media_name_${index}`">Media Name</Label>
                                        <Input :id="`new_media_name_${index}`" v-model="entry.name" placeholder="Media Name" />
                                    </div>
                                    <div class="flex flex-col space-y-1.5">
                                        <Label :for="`new_media_type_${index}`">Media Type</Label>
                                        <Select :id="`new_media_type_${index}`" v-model="entry.type">
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
                                        <Label :for="`new_media_link_${index}`">Link (optional)</Label>
                                        <Input :id="`new_media_link_${index}`" v-model="entry.link" placeholder="https://..." />
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="flex flex-col space-y-1.5">
                            <Label for="characters">Characters</Label>
                            <SearchableMultiselect
                                v-model="formInfo.characters"
                                placeholder="Select Characters"
                                :options="props.characters"
                                option-value="name"
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
