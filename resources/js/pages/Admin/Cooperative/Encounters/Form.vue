<script setup lang="ts">
import LinesField from '@/components/Cooperative/LinesField.vue';
import type { CoopCampaign, CoopPhase, CoopRole } from '@/components/Cooperative/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface EncounterItem {
    id: number;
    number: number;
    name: string;
    slug: string;
    narrative: string | null;
    encounter_size: number | null;
    max_hand_size: number | null;
    board_size: string | null;
    challenge_mode: string | null;
    phases: CoopPhase[] | null;
    campaign: CoopCampaign | null;
    is_published: boolean;
}

const props = defineProps<{
    item: EncounterItem | null;
    next_number: number | null;
    roles: { name: string; value: string }[];
    classes: { slug: string; name: string; role: string }[];
}>();

const NONE = 'none';

/** A phase as the form edits it: the class Select works in strings, so "no class" is NONE until submit. */
type FormPhase = Omit<CoopPhase, 'role_order'> & { role_order: { role: CoopRole; class_slug: string }[] };
const slugify = (s: string) =>
    s
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

const emptyPhase = (): FormPhase => ({
    title: '',
    narrative: '',
    max_hand_size: null,
    role_order: [],
    setup: [],
    special_rules: [],
    victory: [],
    defeat: [],
    progression: [],
});

const form = ref({
    number: props.item?.number ?? props.next_number ?? 1,
    name: props.item?.name ?? '',
    slug: props.item?.slug ?? '',
    narrative: props.item?.narrative ?? '',
    encounter_size: props.item?.encounter_size ?? (null as number | null),
    max_hand_size: props.item?.max_hand_size ?? (null as number | null),
    board_size: props.item?.board_size ?? '',
    challenge_mode: props.item?.challenge_mode ?? '',
    phases: props.item?.phases?.length
        ? (structuredClone(props.item.phases) as CoopPhase[]).map<FormPhase>((p) => ({
              ...emptyPhase(),
              ...p,
              // The Select works in strings; the server stores null for "no suggested class".
              role_order: (p.role_order ?? []).map((r) => ({ ...r, class_slug: r.class_slug ?? NONE })),
          }))
        : [emptyPhase()],
    campaign: { victory: '', defeat: '', aftermath: [] as string[], next_encounter: '', ...(props.item?.campaign ?? {}) } as CoopCampaign,
    is_published: props.item?.is_published ?? false,
});

const slugTouched = ref(!!props.item);
watch(
    () => form.value.name,
    (name) => {
        if (!slugTouched.value) form.value.slug = slugify(name);
    },
);

const errors = computed(() => usePage().props.errors as Record<string, string>);
const otherErrors = computed(() => Object.entries(errors.value).filter(([key]) => key.includes('.')));
const classesFor = (role: string) => props.classes.filter((c) => c.role === role);
const addRole = (phase: FormPhase) => phase.role_order.push({ role: 'brawler', class_slug: NONE });

const sections: { key: 'setup' | 'special_rules' | 'victory' | 'defeat' | 'progression'; label: string }[] = [
    { key: 'setup', label: 'Setup (numbered steps)' },
    { key: 'special_rules', label: 'Special rules' },
    { key: 'victory', label: 'Victory' },
    { key: 'defeat', label: 'Defeat' },
    { key: 'progression', label: 'Progression' },
];

const submit = () => {
    const payload = {
        ...form.value,
        phases: form.value.phases.map((p) => ({
            ...p,
            role_order: (p.role_order ?? []).map((r) => ({ ...r, class_slug: r.class_slug === NONE ? null : r.class_slug })),
        })),
    };
    if (props.item) router.post(route('admin.cooperative.encounters.update', props.item.id), payload);
    else router.post(route('admin.cooperative.encounters.store'), payload);
};
</script>

<template>
    <Head title="Encounter — Admin" />
    <div class="container mx-auto mt-6 space-y-4 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle as="h1">{{ item ? 'Edit' : 'New' }} encounter</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <ul
                    v-if="otherErrors.length"
                    class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                    role="alert"
                >
                    <li v-for="[key, message] in otherErrors" :key="key">{{ message }}</li>
                </ul>

                <div class="grid gap-3 md:grid-cols-[6rem_1fr_1fr]">
                    <div>
                        <Label for="number">Number</Label>
                        <Input id="number" v-model.number="form.number" type="number" min="0" />
                        <InputError :message="errors.number" />
                    </div>
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="errors.name" />
                    </div>
                    <div>
                        <Label for="slug">Slug</Label>
                        <Input id="slug" v-model="form.slug" @input="slugTouched = true" />
                        <InputError :message="errors.slug" />
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <Label for="encounter_size">Encounter size (ss)</Label>
                        <Input id="encounter_size" v-model.number="form.encounter_size" type="number" min="0" />
                    </div>
                    <div>
                        <Label for="max_hand_size">Max hand size</Label>
                        <Input id="max_hand_size" v-model.number="form.max_hand_size" type="number" min="0" />
                    </div>
                    <div>
                        <Label for="board_size">Board size</Label>
                        <Input id="board_size" v-model="form.board_size" placeholder='36" × 36"' />
                    </div>
                </div>
                <div>
                    <Label for="narrative">Narrative text</Label>
                    <Textarea id="narrative" v-model="form.narrative" rows="3" />
                </div>
                <div>
                    <Label for="challenge_mode">Challenge mode</Label>
                    <Textarea id="challenge_mode" v-model="form.challenge_mode" rows="3" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox :checked="form.is_published" @update:checked="(v: boolean) => (form.is_published = v)" />
                    <span>Published (visible on the public Cooperative pages)</span>
                </label>
            </CardContent>
        </Card>

        <Card v-for="(phase, i) in form.phases" :key="i">
            <CardHeader class="flex-row items-center justify-between">
                <CardTitle as="h2" class="text-base"
                    >Phase {{ i + 1
                    }}<span v-if="i > 0" class="ml-2 text-xs font-normal text-muted-foreground">(hidden behind a reveal)</span></CardTitle
                >
                <Button type="button" variant="ghost" size="icon" :aria-label="`Remove phase ${i + 1}`" @click="form.phases.splice(i, 1)"
                    ><Trash2 class="size-4"
                /></Button>
            </CardHeader>
            <CardContent class="space-y-3">
                <div class="grid gap-3 md:grid-cols-[1fr_8rem]">
                    <div>
                        <Label :for="`p${i}-title`">Title</Label>
                        <Input :id="`p${i}-title`" v-model="phase.title" />
                    </div>
                    <div>
                        <Label :for="`p${i}-hand`">Max hand size</Label>
                        <Input :id="`p${i}-hand`" v-model.number="phase.max_hand_size" type="number" min="0" />
                    </div>
                </div>
                <div>
                    <Label :for="`p${i}-narr`">Narrative</Label>
                    <Textarea :id="`p${i}-narr`" v-model="phase.narrative" rows="2" />
                </div>

                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <Label>Role activation order (left to right)</Label>
                        <Button type="button" variant="outline" size="sm" class="gap-1" @click="addRole(phase)"><Plus class="size-4" /> Role</Button>
                    </div>
                    <div v-for="(entry, j) in phase.role_order ?? []" :key="j" class="grid gap-2 sm:grid-cols-[10rem_1fr_auto]">
                        <Select v-model="entry.role">
                            <SelectTrigger :aria-label="`Phase ${i + 1} role ${j + 1}`"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="r in roles" :key="r.value" :value="r.value">{{ r.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="entry.class_slug">
                            <SelectTrigger :aria-label="`Phase ${i + 1} suggested class ${j + 1}`"
                                ><SelectValue placeholder="Suggested class"
                            /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NONE">No suggested class</SelectItem>
                                <SelectItem v-for="c in classesFor(entry.role)" :key="c.slug" :value="c.slug">{{ c.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button type="button" variant="ghost" size="icon" :aria-label="`Remove role ${j + 1}`" @click="phase.role_order?.splice(j, 1)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                </div>

                <div v-for="section in sections" :key="section.key">
                    <Label :for="`p${i}-${section.key}`">{{ section.label }} — one per line</Label>
                    <LinesField :id="`p${i}-${section.key}`" v-model="phase[section.key]" :rows="3" />
                </div>
            </CardContent>
        </Card>
        <Button type="button" variant="outline" class="gap-1" @click="form.phases.push(emptyPhase())"><Plus class="size-4" /> Add phase</Button>

        <Card>
            <CardHeader><CardTitle as="h2" class="text-base">Campaign</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div>
                    <Label for="c-victory">Victory</Label>
                    <Textarea id="c-victory" v-model="form.campaign.victory" rows="2" />
                </div>
                <div>
                    <Label for="c-defeat">Defeat</Label>
                    <Textarea id="c-defeat" v-model="form.campaign.defeat" rows="2" />
                </div>
                <div>
                    <Label for="c-aftermath">Aftermath steps — one per line</Label>
                    <LinesField id="c-aftermath" v-model="form.campaign.aftermath" :rows="4" />
                </div>
                <div>
                    <Label for="c-next">Next encounter</Label>
                    <Textarea id="c-next" v-model="form.campaign.next_encounter" rows="2" />
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.cooperative.encounters.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
