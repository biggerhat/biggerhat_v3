<script setup lang="ts">
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { imageSrc } from '@/composables/useBlueprintImages';
import { router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    blueprint: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
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
    packages: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    name: null as string | null,
    source_url: null as string | null,
    sculpt_version: 'third_edition' as string,
    published_at: null as string | null,
    characters: [] as string[],
    miniatures: [] as string[],
    packages: [] as string[],
});

const submit = () => {
    router.post(
        props.blueprint ? route('admin.blueprints.update', props.blueprint.id) : route('admin.blueprints.store'),
        formInfo.value,
    );
};

onMounted(() => {
    formInfo.value.name = props.blueprint?.name ?? null;
    formInfo.value.source_url = props.blueprint?.source_url ?? null;
    formInfo.value.sculpt_version = props.blueprint?.sculpt_version ?? 'third_edition';
    formInfo.value.published_at = props.blueprint?.published_at?.substring(0, 10) ?? null;

    props.blueprint?.characters?.forEach((c: any) => {
        formInfo.value.characters.push(c.display_name);
    });
    props.blueprint?.miniatures?.forEach((m: any) => {
        formInfo.value.miniatures.push(m.display_name);
    });
    props.blueprint?.packages?.forEach((p: any) => {
        formInfo.value.packages.push(p.name);
    });
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Blueprint</CardTitle>
                <CardDescription>Create and Edit Build Instructions</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <!-- Image preview -->
                        <div v-if="props.blueprint?.image_path" class="flex flex-col space-y-1.5">
                            <Label>Image</Label>
                            <img
                                :src="imageSrc(props.blueprint.image_path)"
                                :alt="props.blueprint.name"
                                class="max-h-48 w-auto rounded-lg border object-contain"
                            />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Blueprint Name" autofocus />
                        </div>

                        <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="sculpt_version">Sculpt Version</Label>
                                <Select id="sculpt_version" v-model="formInfo.sculpt_version">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Sculpt Version" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="v in props.sculpt_versions" :key="v.value" :value="v.value">
                                            {{ v.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="flex flex-col space-y-1.5">
                                <Label for="published_at">Published Date</Label>
                                <Input id="published_at" v-model="formInfo.published_at" type="date" />
                            </div>
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label for="source_url">Source URL</Label>
                            <Input id="source_url" v-model="formInfo.source_url" placeholder="https://..." />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label>Characters</Label>
                            <SearchableMultiselect v-model="formInfo.characters" placeholder="Search characters..." :options="props.characters" option-value="name" />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label>Miniatures</Label>
                            <SearchableMultiselect v-model="formInfo.miniatures" placeholder="Search miniatures..." :options="props.miniatures" option-value="name" />
                        </div>

                        <div class="flex flex-col space-y-1.5">
                            <Label>Packages</Label>
                            <SearchableMultiselect v-model="formInfo.packages" placeholder="Search packages..." :options="props.packages" option-value="name" />
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.blueprints.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
