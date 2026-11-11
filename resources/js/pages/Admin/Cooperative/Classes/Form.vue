<script setup lang="ts">
import LinesField from '@/components/Cooperative/LinesField.vue';
import type { CoopAction, CoopBehaviorRow } from '@/components/Cooperative/types';
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

interface ClassItem {
    id: number;
    name: string;
    slug: string;
    role: string;
    is_unique: boolean;
    resistance: number;
    speed: number | null;
    max_health: number;
    elite_max_health: number | null;
    abilities: { name: string; text: string }[] | null;
    actions: CoopAction[] | null;
    behavior: CoopBehaviorRow[] | null;
    target_preference: string[] | null;
    elite_modifications: string | null;
    is_published: boolean;
    sort_order: number;
}

const props = defineProps<{ item: ClassItem | null; roles: { name: string; value: string }[] }>();

const slugify = (s: string) =>
    s
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

const form = ref({
    name: props.item?.name ?? '',
    slug: props.item?.slug ?? '',
    role: props.item?.role ?? 'brawler',
    is_unique: props.item?.is_unique ?? false,
    resistance: props.item?.resistance ?? 10,
    speed: props.item?.speed ?? (null as number | null),
    max_health: props.item?.max_health ?? 5,
    elite_max_health: props.item?.elite_max_health ?? (null as number | null),
    abilities: structuredClone(props.item?.abilities ?? []) as { name: string; text: string }[],
    actions: structuredClone(props.item?.actions ?? []).map((a) => ({ ...a, triggers: a.triggers ?? [] })) as CoopAction[],
    behavior: structuredClone(props.item?.behavior ?? []) as CoopBehaviorRow[],
    target_preference: [...(props.item?.target_preference ?? [])],
    elite_modifications: props.item?.elite_modifications ?? '',
    is_published: props.item?.is_published ?? true,
    sort_order: props.item?.sort_order ?? 0,
});

// New classes get a slug from the name until it is edited by hand.
const slugTouched = ref(!!props.item);
watch(
    () => form.value.name,
    (name) => {
        if (!slugTouched.value) form.value.slug = slugify(name);
    },
);

const errors = computed(() => usePage().props.errors as Record<string, string>);
const otherErrors = computed(() => Object.entries(errors.value).filter(([key]) => key.includes('.')));

const addAbility = () => form.value.abilities.push({ name: '', text: '' });
const addAction = (kind: 'attack' | 'tactical') =>
    form.value.actions.push({ kind, name: '', rg: '', skl: '', rst: '', tn: '', dmg: '', text: '', triggers: [] });
const addBehavior = () => form.value.behavior.push({ flip_min: null, flip_max: null, ideal_range: '', effects: [] });

const submit = () => {
    if (props.item) router.post(route('admin.cooperative.classes.update', props.item.id), form.value);
    else router.post(route('admin.cooperative.classes.store'), form.value);
};
</script>

<template>
    <Head title="NP Class — Admin" />
    <div class="container mx-auto mt-6 space-y-4 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle as="h1">{{ item ? 'Edit' : 'New' }} NP class</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <ul
                    v-if="otherErrors.length"
                    class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive"
                    role="alert"
                >
                    <li v-for="[key, message] in otherErrors" :key="key">{{ message }}</li>
                </ul>

                <div class="grid gap-3 md:grid-cols-3">
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
                    <div>
                        <Label for="role">Role</Label>
                        <Select id="role" v-model="form.role">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="r in roles" :key="r.value" :value="r.value">{{ r.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.role" />
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4">
                    <div>
                        <Label for="resistance">Resistance (TN)</Label>
                        <Input id="resistance" v-model.number="form.resistance" type="number" min="0" />
                        <InputError :message="errors.resistance" />
                    </div>
                    <div>
                        <Label for="speed">Speed (blank = none)</Label>
                        <Input id="speed" v-model.number="form.speed" type="number" min="0" />
                    </div>
                    <div>
                        <Label for="max_health">Max health</Label>
                        <Input id="max_health" v-model.number="form.max_health" type="number" min="1" />
                        <InputError :message="errors.max_health" />
                    </div>
                    <div>
                        <Label for="elite_max_health">Elite max health</Label>
                        <Input id="elite_max_health" v-model.number="form.elite_max_health" type="number" min="1" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-6">
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox :checked="form.is_unique" @update:checked="(v: boolean) => (form.is_unique = v)" />
                        <span>Unique (written for specific encounters)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox :checked="form.is_published" @update:checked="(v: boolean) => (form.is_published = v)" />
                        <span>Published</span>
                    </label>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex-row items-center justify-between">
                <CardTitle as="h2" class="text-base">Abilities</CardTitle>
                <Button type="button" variant="outline" size="sm" class="gap-1" @click="addAbility"><Plus class="size-4" /> Add</Button>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-for="(ability, i) in form.abilities" :key="i" class="grid gap-2 md:grid-cols-[14rem_1fr_auto]">
                    <Input v-model="ability.name" placeholder="Name" :aria-label="`Ability ${i + 1} name`" />
                    <Textarea v-model="ability.text" rows="2" placeholder="Text" :aria-label="`Ability ${i + 1} text`" />
                    <Button type="button" variant="ghost" size="icon" :aria-label="`Remove ability ${i + 1}`" @click="form.abilities.splice(i, 1)"
                        ><Trash2 class="size-4"
                    /></Button>
                </div>
                <p v-if="!form.abilities.length" class="text-sm text-muted-foreground">No abilities.</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex-row items-center justify-between">
                <CardTitle as="h2" class="text-base">Actions</CardTitle>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" class="gap-1" @click="addAction('attack')"
                        ><Plus class="size-4" /> Attack</Button
                    >
                    <Button type="button" variant="outline" size="sm" class="gap-1" @click="addAction('tactical')"
                        ><Plus class="size-4" /> Tactical</Button
                    >
                </div>
            </CardHeader>
            <CardContent class="space-y-4">
                <div v-for="(action, i) in form.actions" :key="i" class="space-y-2 rounded-md border p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ action.kind }}</span>
                        <Button type="button" variant="ghost" size="icon" :aria-label="`Remove action ${i + 1}`" @click="form.actions.splice(i, 1)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3 md:grid-cols-6">
                        <Input v-model="action.name" class="sm:col-span-3 md:col-span-2" placeholder="Name" :aria-label="`Action ${i + 1} name`" />
                        <Input v-model="action.rg" placeholder='Rg (12")' :aria-label="`Action ${i + 1} range`" />
                        <Input v-model="action.skl" placeholder="Skl" :aria-label="`Action ${i + 1} skill`" />
                        <Input v-model="action.rst" placeholder="Rst" :aria-label="`Action ${i + 1} resist`" />
                        <Input v-model="action.tn" placeholder="TN" :aria-label="`Action ${i + 1} TN`" />
                        <Input v-model="action.dmg" placeholder="Dmg" :aria-label="`Action ${i + 1} damage`" />
                    </div>
                    <Textarea v-model="action.text" rows="2" placeholder="Effect text" :aria-label="`Action ${i + 1} text`" />
                    <div v-for="(trigger, j) in action.triggers ?? []" :key="j" class="grid gap-2 md:grid-cols-[14rem_1fr_auto]">
                        <Input v-model="trigger.name" placeholder="Trigger name" :aria-label="`Action ${i + 1} trigger ${j + 1} name`" />
                        <Textarea v-model="trigger.text" rows="2" placeholder="Trigger text" :aria-label="`Action ${i + 1} trigger ${j + 1} text`" />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove trigger ${j + 1}`"
                            @click="action.triggers?.splice(j, 1)"
                            ><Trash2 class="size-4"
                        /></Button>
                    </div>
                    <Button type="button" variant="outline" size="sm" class="gap-1" @click="(action.triggers ??= []).push({ name: '', text: '' })"
                        ><Plus class="size-4" /> Trigger</Button
                    >
                </div>
                <p v-if="!form.actions.length" class="text-sm text-muted-foreground">No actions.</p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader class="flex-row items-center justify-between">
                <CardTitle as="h2" class="text-base">Behavior chart</CardTitle>
                <Button type="button" variant="outline" size="sm" class="gap-1" @click="addBehavior"><Plus class="size-4" /> Row</Button>
            </CardHeader>
            <CardContent class="space-y-3">
                <p class="text-xs text-muted-foreground">
                    One row per flip range, top to bottom as printed. Leave "to" blank for an open range such as 13+. Effects: one per line, in order.
                </p>
                <div v-for="(row, i) in form.behavior" :key="i" class="grid gap-2 md:grid-cols-[5rem_5rem_6rem_1fr_auto]">
                    <Input
                        v-model.number="row.flip_min"
                        type="number"
                        min="1"
                        max="13"
                        placeholder="Flip from"
                        :aria-label="`Row ${i + 1} flip from`"
                    />
                    <Input v-model.number="row.flip_max" type="number" min="1" max="13" placeholder="to" :aria-label="`Row ${i + 1} flip to`" />
                    <Input v-model="row.ideal_range" placeholder='Ideal (8")' :aria-label="`Row ${i + 1} ideal range`" />
                    <LinesField v-model="row.effects" :rows="3" placeholder="Walk&#10;Hail of Gunfire" />
                    <Button type="button" variant="ghost" size="icon" :aria-label="`Remove row ${i + 1}`" @click="form.behavior.splice(i, 1)"
                        ><Trash2 class="size-4"
                    /></Button>
                </div>
                <p v-if="!form.behavior.length" class="text-sm text-muted-foreground">No behavior rows.</p>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="space-y-3 pt-6">
                <div>
                    <Label for="target_preference">Target preference (one per line, in order)</Label>
                    <LinesField
                        id="target_preference"
                        v-model="form.target_preference"
                        :rows="3"
                        placeholder="Lowest current health&#10;Lowest Df&#10;Closest"
                    />
                </div>
                <div>
                    <Label for="elite_modifications">Elite modifications</Label>
                    <Textarea id="elite_modifications" v-model="form.elite_modifications" rows="2" />
                </div>
                <div class="max-w-[10rem]">
                    <Label for="sort_order">Sort order</Label>
                    <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" />
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.cooperative.classes.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
