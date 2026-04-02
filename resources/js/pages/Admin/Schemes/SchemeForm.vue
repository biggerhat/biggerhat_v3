<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    scheme: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    schemes: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    seasons: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
});

interface Requirement {
    type: 'select_model' | 'select_marker' | 'terrain_note';
    allegiance?: 'enemy' | 'friendly' | null;
    unique?: boolean;
    cost_operator?: '>' | '<' | '>=' | '<=' | null;
    cost_value?: number | null;
}

const requirementTypes = [
    { value: 'select_model', label: 'Select Model' },
    { value: 'select_marker', label: 'Select Marker Type' },
    { value: 'terrain_note', label: 'Note Terrain Piece' },
];

const formInfo = ref({
    name: null,
    season: null,
    selector: null,
    prerequisite: null,
    reveal: null,
    scoring: null,
    additional: null,
    requirements: [] as Requirement[],
    image: null,
    next_scheme_one_id: null,
    next_scheme_two_id: null,
    next_scheme_three_id: null,
});

const addRequirement = (type: string) => {
    const req: Requirement = { type: type as Requirement['type'] };
    if (type === 'select_model') {
        req.allegiance = 'enemy';
        req.unique = false;
        req.cost_operator = null;
        req.cost_value = null;
    }
    formInfo.value.requirements.push(req);
};

const removeRequirement = (idx: number) => {
    formInfo.value.requirements.splice(idx, 1);
};

const requirementLabel = (req: Requirement): string => {
    if (req.type === 'select_marker') return 'Select Marker Type';
    if (req.type === 'terrain_note') return 'Note Terrain Piece';
    const parts: string[] = [];
    if (req.unique) parts.push('Unique');
    parts.push(req.allegiance === 'friendly' ? 'Friendly' : 'Enemy');
    parts.push('Model');
    if (req.cost_operator && req.cost_value != null) {
        parts.push(`(Cost ${req.cost_operator} ${req.cost_value})`);
    }
    return parts.join(' ');
};

const submit = () => {
    router.post(props.scheme ? route('admin.schemes.update', props.scheme.slug) : route('admin.schemes.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.scheme?.name ?? null;
    formInfo.value.season = props.scheme?.season ?? null;
    formInfo.value.selector = props.scheme?.selector ?? null;
    formInfo.value.prerequisite = props.scheme?.prerequisite ?? null;
    formInfo.value.reveal = props.scheme?.reveal ?? null;
    formInfo.value.scoring = props.scheme?.scoring ?? null;
    formInfo.value.additional = props.scheme?.additional ?? null;
    formInfo.value.requirements = props.scheme?.requirements ?? [];
    formInfo.value.next_scheme_one_id = props.scheme?.next_scheme_one_id ?? null;
    formInfo.value.next_scheme_two_id = props.scheme?.next_scheme_two_id ?? null;
    formInfo.value.next_scheme_three_id = props.scheme?.next_scheme_three_id ?? null;
});
</script>

<template>
    <Head title="Scheme" />
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Scheme</CardTitle>
                <CardDescription>Create and Edit Scheme Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="formInfo.name" placeholder="Scheme Name" />
                            <InputError :message="usePage().props.errors.name" />
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
                            <InputError :message="usePage().props.errors.season" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="selector">Selector</Label>
                            <Input id="selector" v-model="formInfo.selector" placeholder="Scheme Selector (e.g. Masks, Tomes)" />
                            <InputError :message="usePage().props.errors.selector" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="prerequisite">Prerequisite Info</Label>
                            <Textarea id="prerequisite" v-model="formInfo.prerequisite" placeholder="Type the prerequisite info here." />
                            <InputError :message="usePage().props.errors.prerequisite" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="reveal">Reveal</Label>
                            <Textarea id="reveal" v-model="formInfo.reveal" placeholder="Type the reveal info here." />
                            <InputError :message="usePage().props.errors.reveal" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="scoring">Scoring</Label>
                            <Textarea id="scoring" v-model="formInfo.scoring" placeholder="Type the scoring info here." />
                            <InputError :message="usePage().props.errors.scoring" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="additional">Additional VP</Label>
                            <Textarea id="additional" v-model="formInfo.additional" placeholder="Type the additional vp info here." />
                            <InputError :message="usePage().props.errors.additional" />
                        </div>
                        <div class="flex flex-col space-y-3">
                            <Label>Game Tracker Requirements</Label>
                            <p class="text-sm text-muted-foreground">Define what info the player needs to track when using this scheme.</p>

                            <!-- Existing requirements -->
                            <div v-for="(req, idx) in formInfo.requirements" :key="idx" class="rounded-md border p-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium">{{ requirementLabel(req) }}</span>
                                    <button type="button" class="text-xs text-destructive hover:underline" @click="removeRequirement(idx)">Remove</button>
                                </div>

                                <!-- Model-specific options -->
                                <div v-if="req.type === 'select_model'" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label class="text-xs">Allegiance</Label>
                                        <Select v-model="req.allegiance">
                                            <SelectTrigger class="h-8 text-xs"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="enemy">Enemy</SelectItem>
                                                <SelectItem value="friendly">Friendly</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-2 text-xs">
                                            <input type="checkbox" v-model="req.unique" class="accent-primary" />
                                            Must be Unique
                                        </label>
                                    </div>
                                    <div>
                                        <Label class="text-xs">Cost Condition</Label>
                                        <Select v-model="req.cost_operator">
                                            <SelectTrigger class="h-8 text-xs"><SelectValue placeholder="None" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">None</SelectItem>
                                                <SelectItem value=">">Greater than (&gt;)</SelectItem>
                                                <SelectItem value="<">Less than (&lt;)</SelectItem>
                                                <SelectItem value=">=">At least (&ge;)</SelectItem>
                                                <SelectItem value="<=">At most (&le;)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div v-if="req.cost_operator && req.cost_operator !== 'none'">
                                        <Label class="text-xs">Cost Value</Label>
                                        <Input v-model.number="req.cost_value" type="number" min="0" max="20" class="h-8 text-xs" />
                                    </div>
                                </div>
                            </div>

                            <!-- Add requirement -->
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="rt in requirementTypes"
                                    :key="rt.value"
                                    type="button"
                                    class="rounded-md border px-3 py-1.5 text-xs transition-colors hover:bg-muted"
                                    @click="addRequirement(rt.value)"
                                >
                                    + {{ rt.label }}
                                </button>
                            </div>
                            <InputError :message="usePage().props.errors.requirements" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex w-full max-w-sm flex-col items-center gap-1.5 space-y-1.5">
                                <img
                                    v-if="props.scheme?.image && !formInfo.image"
                                    :src="'/storage/' + props.scheme.image"
                                    :alt="props.scheme?.name"
                                    class="w-full rounded-lg"
                                />
                                <Label for="image">Image</Label>
                                <Input
                                    id="image"
                                    type="file"
                                    accept=".heic, .jpeg, .jpg, .png, .webp"
                                    @input="formInfo.image = $event.target.files[0]"
                                />
                                <InputError :message="usePage().props.errors.image" />
                            </div>
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
                                    <InputError :message="usePage().props.errors.next_scheme_one_id" />
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
                                    <InputError :message="usePage().props.errors.next_scheme_two_id" />
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
                                    <InputError :message="usePage().props.errors.next_scheme_three_id" />
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.schemes.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
