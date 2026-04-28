<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { TosSelectOption } from '@/types/tos';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface ActionRow {
    id: number;
    slug: string;
    name: string;
    av: number | null;
    av_target: string | null;
    av_suits: string | null;
    tn: number | null;
    range: string | null;
    strength: number | null;
    is_piercing: boolean;
    is_accurate: boolean;
    is_area: boolean;
    usage_limit: string | null;
    body: string | null;
    type_links: Array<{ id: number; action_id: number; type: string; sort_order: number }>;
}

const props = defineProps<{
    action?: ActionRow | null;
    action_types: TosSelectOption[];
    usage_limits: TosSelectOption[];
}>();

const formInfo = ref({
    name: '' as string,
    types: [] as string[],
    av: null as number | null,
    av_target: null as string | null,
    av_suits: null as string | null,
    tn: null as number | null,
    range: null as string | null,
    strength: null as number | null,
    is_piercing: false as boolean,
    is_accurate: false as boolean,
    is_area: false as boolean,
    usage_limit: null as string | null,
    body: null as string | null,
});

function toggleType(value: string) {
    const i = formInfo.value.types.indexOf(value);
    if (i === -1) formInfo.value.types.push(value);
    else formInfo.value.types.splice(i, 1);
}

const submit = () => {
    if (props.action) router.post(route('admin.tos.actions.update', props.action.slug), formInfo.value);
    else router.post(route('admin.tos.actions.store'), formInfo.value);
};

onMounted(() => {
    if (!props.action) return;
    Object.assign(formInfo.value, {
        name: props.action.name,
        types: (props.action.type_links ?? []).map((l) => l.type),
        av: props.action.av,
        av_target: props.action.av_target,
        av_suits: props.action.av_suits,
        tn: props.action.tn,
        range: props.action.range,
        strength: props.action.strength,
        is_piercing: props.action.is_piercing,
        is_accurate: props.action.is_accurate,
        is_area: props.action.is_area,
        usage_limit: props.action.usage_limit,
        body: props.action.body,
    });
});
</script>

<template>
    <Head title="TOS Action — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader><CardTitle>{{ action ? 'Edit Action' : 'New Action' }}</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="formInfo.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div class="md:col-span-2">
                        <Label>Types (one or more — rulebook p. 22)</Label>
                        <div class="mt-1 flex flex-wrap gap-3 rounded-md border p-3">
                            <label v-for="t in action_types" :key="t.value" class="flex items-center gap-1.5 text-xs">
                                <Checkbox :checked="formInfo.types.includes(t.value)" @update:checked="toggleType(t.value)" />
                                {{ t.name }}
                            </label>
                        </div>
                        <InputError :message="usePage().props.errors.types" />
                    </div>
                    <div>
                        <Label for="av">AV</Label>
                        <Input id="av" v-model.number="formInfo.av" type="number" />
                    </div>
                    <div>
                        <Label for="av_suits">AV suit(s) e.g. R</Label>
                        <Input id="av_suits" v-model="formInfo.av_suits" maxlength="8" />
                    </div>
                    <div>
                        <Label for="av_target">AV vs (Df/Wp/etc)</Label>
                        <Input id="av_target" v-model="formInfo.av_target" maxlength="8" />
                    </div>
                    <div>
                        <Label for="tn">TN (simple-duel)</Label>
                        <Input id="tn" v-model.number="formInfo.tn" type="number" />
                    </div>
                    <div>
                        <Label for="range">Range</Label>
                        <Input id="range" v-model="formInfo.range" placeholder='6", y, self' />
                    </div>
                    <div>
                        <Label for="strength">Strength</Label>
                        <Input id="strength" v-model.number="formInfo.strength" type="number" min="0" />
                    </div>
                    <div>
                        <Label for="usage_limit">Usage Limit</Label>
                        <Select v-model="formInfo.usage_limit">
                            <SelectTrigger><SelectValue placeholder="No limit" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="u in usage_limits" :key="u.value" :value="u.value">{{ u.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-4 rounded-md border p-3">
                    <label class="flex items-center gap-1.5 text-xs">
                        <Checkbox v-model:checked="formInfo.is_piercing" /> Piercing
                    </label>
                    <label class="flex items-center gap-1.5 text-xs">
                        <Checkbox v-model:checked="formInfo.is_accurate" /> Accurate
                    </label>
                    <label class="flex items-center gap-1.5 text-xs">
                        <Checkbox v-model:checked="formInfo.is_area" /> Area
                    </label>
                </div>

                <div>
                    <Label for="body">Effect</Label>
                    <Textarea id="body" v-model="formInfo.body" />
                </div>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.actions.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
