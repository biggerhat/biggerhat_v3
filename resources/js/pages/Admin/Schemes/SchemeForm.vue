<script setup lang='ts'>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {Select, SelectContent, SelectItem, SelectTrigger, SelectValue} from "@/components/ui/select";

const props = defineProps({
    scheme: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        }
    },
    schemes: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    },
    seasons: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        }
    }
});

const formInfo = ref({
    name: null,
    season: null,
    selector: null,
    prerequisite: null,
    reveal: null,
    scoring: null,
    additional: null,
    next_scheme_one_id: null,
    next_scheme_two_id: null,
    next_scheme_three_id: null,
});

const submit = () => {
    router.post(props.scheme ? route("admin.schemes.update", props.scheme.slug) : route("admin.schemes.store"),
        formInfo.value
    );
};

onMounted(() => {
    formInfo.value.name = props.scheme?.name ?? null;
    formInfo.value.season = props.scheme?.season ?? null;
    formInfo.value.selector = props.scheme?.selector ?? null;
    formInfo.value.prerequisite = props.scheme?.prerequisite ?? null;
    formInfo.value.reveal = props.scheme?.reveal ?? null;
    formInfo.value.scoring = props.scheme?.scoring ?? null;
    formInfo.value.additional = props.scheme?.additional ?? null;
    formInfo.value.next_scheme_one_id = props.scheme?.next_scheme_one_id ?? null;
    formInfo.value.next_scheme_two_id = props.scheme?.next_scheme_two_id ?? null;
    formInfo.value.next_scheme_three_id = props.scheme?.next_scheme_three_id ?? null;
});
</script>

<template>
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Scheme</CardTitle>
                <CardDescription>Create and Edit Scheme Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid items-center w-full gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Scheme Name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="season">Season</Label>
                            <Select id="season" v-model="formInfo.season">
                                <SelectTrigger>
                                    <SelectValue placeholder="Gameplay Season" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="season in props.seasons" :value="season.value" :key="season.value">
                                        {{ season.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="prerequisite">Prerequisite Info</Label>
                            <Textarea id="prerequisite" v-model="formInfo.prerequisite" placeholder="Type the prerequisite info here." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="reveal">Reveal</Label>
                            <Textarea id="reveal" v-model="formInfo.reveal" placeholder="Type the reveal info here." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="scoring">Scoring</Label>
                            <Textarea id="scoring" v-model="formInfo.scoring" placeholder="Type the scoring info here." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="additional">Additional VP</Label>
                            <Textarea id="additional" v-model="formInfo.additional" placeholder="Type the additional vp info here." />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="next_scheme_one_id">Next Scheme</Label>
                                    <Select id="next_scheme_one_id" v-model="formInfo.next_scheme_one_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Scheme" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="scheme in props.schemes" :value="scheme.value" :key="scheme.value">
                                                {{ scheme.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="next_scheme_two_id">Next Scheme</Label>
                                    <Select id="next_scheme_two_id" v-model="formInfo.next_scheme_two_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Scheme" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="scheme in props.schemes" :value="scheme.value" :key="scheme.value">
                                                {{ scheme.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="next_scheme_three_id">Next Scheme</Label>
                                    <Select id="next_scheme_three_id" v-model="formInfo.next_scheme_three_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select Scheme" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem v-for="scheme in props.schemes" :value="scheme.value" :key="scheme.value">
                                                {{ scheme.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.schemes.index'))" variant="outline">
                    Cancel
                </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
