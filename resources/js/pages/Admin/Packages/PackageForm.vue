<script setup lang="ts">
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import TextBar from '@/components/TextBar.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { router } from '@inertiajs/vue3';
import { CircleX } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

const props = defineProps({
    package: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    factions: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    sculpt_versions: {
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
    miniatures: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    keywords: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    name: null,
    description: null,
    factions: [],
    sku: null,
    upc: null,
    msrp: null,
    distributor_description: null,
    sculpt_version: null,
    is_preassembled: false,
    released_at: null,
    front_image: null,
    back_image: null,
    combination_image: null,
    characters: [],
    miniatures: [],
    keywords: [],
});

const submit = () => {
    router.post(props.package ? route('admin.packages.update', props.package.slug) : route('admin.packages.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.package?.name ?? null;
    formInfo.value.description = props.package?.description ?? null;
    formInfo.value.factions = props.package?.factions ?? [];
    formInfo.value.sku = props.package?.sku ?? null;
    formInfo.value.upc = props.package?.upc ?? null;
    formInfo.value.msrp = props.package?.msrp ?? null;
    formInfo.value.distributor_description = props.package?.distributor_description ?? null;
    formInfo.value.sculpt_version = props.package?.sculpt_version ?? null;
    formInfo.value.is_preassembled = props.package?.is_preassembled ?? false;
    formInfo.value.released_at = props.package?.released_at?.split('T')[0] ?? null;

    props.package?.characters.forEach((character) => {
        formInfo.value.characters.push(character.display_name);
    });

    props.package?.miniatures.forEach((miniature) => {
        formInfo.value.miniatures.push(miniature.display_name);
    });

    props.package?.keywords.forEach((keyword) => {
        formInfo.value.keywords.push(keyword.name);
    });
});
</script>

<template>
    <div class="container mx-auto mt-6 h-full px-2">
        <Card>
            <CardHeader>
                <CardTitle>Package</CardTitle>
                <CardDescription>Create and Edit Package Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" autofocus placeholder="Package Name" />
                        </div>

                        <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="sku">SKU</Label>
                                <Input id="sku" v-model="formInfo.sku" placeholder="SKU" />
                            </div>
                            <div class="flex flex-col space-y-1.5">
                                <Label for="upc">UPC</Label>
                                <Input id="upc" v-model="formInfo.upc" placeholder="UPC" />
                            </div>
                            <div class="flex flex-col space-y-1.5">
                                <Label for="msrp">MSRP (cents)</Label>
                                <Input id="msrp" v-model="formInfo.msrp" type="number" placeholder="MSRP" />
                            </div>
                            <div class="my-auto flex w-full flex-col space-y-1.5">
                                <Label for="sculpt_version">Sculpt Version</Label>
                                <div class="my-auto flex w-full">
                                    <Select id="sculpt_version" v-model="formInfo.sculpt_version" class="inline">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Sculpt Version" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="sv in props.sculpt_versions" :value="sv.value" :key="sv.value">
                                                {{ sv.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <CircleX
                                        class="my-auto ml-2 text-destructive"
                                        v-if="formInfo.sculpt_version"
                                        @click="formInfo.sculpt_version = null"
                                    />
                                </div>
                            </div>
                            <div class="flex flex-col space-y-1.5">
                                <Label for="released_at">Release Date</Label>
                                <Input id="released_at" v-model="formInfo.released_at" type="date" />
                            </div>
                            <div class="flex items-center space-x-2 pt-6">
                                <Checkbox id="is_preassembled" v-model:checked="formInfo.is_preassembled" />
                                <Label for="is_preassembled">Pre-assembled</Label>
                            </div>
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label for="description">Description</Label>
                            <Textarea id="description" v-model="formInfo.description" placeholder="Package description" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="distributor_description">Distributor Description</Label>
                            <Textarea id="distributor_description" v-model="formInfo.distributor_description" placeholder="Distributor description" />
                        </div>

                        <TextBar text="Factions" />
                        <div class="flex flex-col space-y-1.5">
                            <SearchableMultiselect
                                v-model="formInfo.factions"
                                placeholder="Select Factions"
                                :options="props.factions"
                            />
                        </div>

                        <TextBar text="Images" />
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="mx-auto flex w-full max-w-sm flex-col items-center gap-1.5 space-y-1.5">
                                    <Label v-if="props.package?.front_image && !formInfo.front_image" for="current_front_image"
                                        >Current Front Image</Label
                                    >
                                    <img
                                        id="current_front_image"
                                        v-if="props.package?.front_image && !formInfo.front_image"
                                        :src="'/storage/' + props.package?.front_image"
                                        :alt="props.package?.name"
                                        class="max-h-48 w-auto rounded-lg border object-contain"
                                    />
                                    <Label for="front_image">Front Image</Label>
                                    <Input
                                        id="front_image"
                                        type="file"
                                        accept=".heic, .jpeg, .jpg, .png, .webp"
                                        @input="formInfo.front_image = $event.target.files[0]"
                                    />
                                </div>
                                <div class="mx-auto flex w-full max-w-sm flex-col items-center gap-1.5 space-y-1.5">
                                    <Label v-if="props.package?.back_image && !formInfo.back_image" for="current_back_image"
                                        >Current Back Image</Label
                                    >
                                    <img
                                        id="current_back_image"
                                        v-if="props.package?.back_image && !formInfo.back_image"
                                        :src="'/storage/' + props.package?.back_image"
                                        :alt="props.package?.name"
                                        class="max-h-48 w-auto rounded-lg border object-contain"
                                    />
                                    <Label for="back_image">Back Image</Label>
                                    <Input
                                        id="back_image"
                                        type="file"
                                        accept=".heic, .jpeg, .jpg, .png, .webp"
                                        @input="formInfo.back_image = $event.target.files[0]"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="mx-auto flex w-full max-w-sm flex-col items-center gap-1.5 space-y-1.5">
                                <Label v-if="props.package?.combination_image && !formInfo.combination_image" for="current_combo_image"
                                    >Current Combination Image</Label
                                >
                                <img
                                    id="current_combo_image"
                                    v-if="props.package?.combination_image && !formInfo.combination_image"
                                    :src="'/storage/' + props.package?.combination_image"
                                    :alt="props.package?.name"
                                    class="max-h-48 w-auto rounded-lg border object-contain"
                                />
                                <Label for="combination_image">Combination Image (Optional Override)</Label>
                                <Input
                                    id="combination_image"
                                    type="file"
                                    accept=".heic, .jpeg, .jpg, .png, .webp"
                                    @input="formInfo.combination_image = $event.target.files[0]"
                                />
                            </div>
                        </div>

                        <TextBar text="Related" />
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="characters">Characters</Label>
                                    <SearchableMultiselect
                                        v-model="formInfo.characters"
                                        placeholder="Select Characters"
                                        :options="props.characters"
                                        option-value="name"
                                    />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="miniatures">Miniatures</Label>
                                    <SearchableMultiselect
                                        v-model="formInfo.miniatures"
                                        placeholder="Select Miniatures"
                                        :options="props.miniatures"
                                        option-value="name"
                                    />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="keywords">Keywords</Label>
                                    <SearchableMultiselect
                                        v-model="formInfo.keywords"
                                        placeholder="Select Keywords"
                                        :options="props.keywords"
                                        option-value="name"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-end gap-2 px-6 pb-6">
                <Button @click="router.get(route('admin.packages.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
