<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface LimitRow {
    limit_type: string;
    parameter_type: string | null;
    parameter_value: string | null;
    parameter_unit_id: number | null;
    parameter_allegiance_id: number | null;
    notes: string | null;
}

interface AssetRow {
    id: number;
    name: string;
    scrip_cost: number;
    disable_count: number | null;
    scrap_count: number | null;
    body: string | null;
    image_path: string | null;
    sort_order: number;
    allegiances: Array<{ id: number }>;
    abilities: Array<{ id: number }>;
    actions: Array<{ id: number }>;
    limits: LimitRow[];
}

const props = defineProps<{
    asset?: AssetRow | null;
    allegiances: Array<{ id: number; name: string }>;
    units: Array<{ id: number; name: string }>;
    abilities: Array<{ id: number; name: string }>;
    actions: Array<{ id: number; name: string; type_links: Array<{ id: number; type: string }> }>;
    limit_types: Array<{ name: string; value: string }>;
    parameter_types: Array<{ name: string; value: string }>;
}>();

const blankLimit = (): LimitRow => ({
    limit_type: 'unique',
    parameter_type: null,
    parameter_value: null,
    parameter_unit_id: null,
    parameter_allegiance_id: null,
    notes: null,
});

const formInfo = ref({
    name: '' as string,
    scrip_cost: 1 as number,
    disable_count: null as number | null,
    scrap_count: null as number | null,
    body: null as string | null,
    image_path: null as File | null,
    sort_order: 0 as number,
    allegiance_ids: [] as string[],
    ability_ids: [] as string[],
    action_ids: [] as string[],
    limits: [] as LimitRow[],
});

const existingImage = computed<string | null>(() => {
    const path = props.asset?.image_path;
    if (!path) return null;
    return path.startsWith('/') || path.startsWith('http') ? path : `/storage/${path}`;
});

const actionOptions = computed(() =>
    props.actions.map((a) => ({
        id: a.id,
        name: a.type_links?.length ? `${a.name} (${a.type_links.map((l) => l.type).join(', ')})` : a.name,
    })),
);

function addLimit() {
    formInfo.value.limits.push(blankLimit());
}

function removeLimit(idx: number) {
    formInfo.value.limits.splice(idx, 1);
}

const toInt = (v: string) => Number.parseInt(v, 10);

const submit = () => {
    const payload = {
        ...formInfo.value,
        allegiance_ids: formInfo.value.allegiance_ids.map(toInt),
        ability_ids: formInfo.value.ability_ids.map(toInt),
        action_ids: formInfo.value.action_ids.map(toInt),
    };
    if (props.asset) router.post(route('admin.tos.assets.update', props.asset.slug), payload);
    else router.post(route('admin.tos.assets.store'), payload);
};

onMounted(() => {
    if (!props.asset) return;
    formInfo.value.name = props.asset.name;
    formInfo.value.scrip_cost = props.asset.scrip_cost;
    formInfo.value.disable_count = props.asset.disable_count;
    formInfo.value.scrap_count = props.asset.scrap_count;
    formInfo.value.body = props.asset.body;
    formInfo.value.sort_order = props.asset.sort_order;
    formInfo.value.allegiance_ids = props.asset.allegiances.map((a) => String(a.id));
    formInfo.value.ability_ids = props.asset.abilities.map((a) => String(a.id));
    formInfo.value.action_ids = props.asset.actions.map((a) => String(a.id));
    formInfo.value.limits = props.asset.limits.map((l) => ({
        limit_type: l.limit_type,
        parameter_type: l.parameter_type,
        parameter_value: l.parameter_value,
        parameter_unit_id: l.parameter_unit_id,
        parameter_allegiance_id: l.parameter_allegiance_id,
        notes: l.notes,
    }));
});
</script>

<template>
    <Head title="TOS Asset — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader><CardTitle>{{ asset ? 'Edit Asset' : 'New Asset' }}</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="formInfo.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="scrip_cost">Scrip Cost</Label>
                        <Input id="scrip_cost" v-model.number="formInfo.scrip_cost" type="number" />
                    </div>
                    <div>
                        <Label for="disable_count">Disable #</Label>
                        <Input id="disable_count" v-model.number="formInfo.disable_count" type="number" min="0" />
                    </div>
                    <div>
                        <Label for="scrap_count">Scrap #</Label>
                        <Input id="scrap_count" v-model.number="formInfo.scrap_count" type="number" min="0" />
                    </div>
                    <div>
                        <Label for="sort_order">Sort Order</Label>
                        <Input id="sort_order" v-model.number="formInfo.sort_order" type="number" min="0" />
                    </div>
                </div>

                <div>
                    <Label for="body">Body</Label>
                    <Textarea id="body" v-model="formInfo.body" />
                </div>

                <div class="space-y-1.5">
                    <Label for="image_path">Card Image</Label>
                    <img
                        v-if="existingImage"
                        :src="existingImage"
                        :alt="formInfo.name || 'Current image'"
                        class="h-40 w-auto rounded border object-cover"
                    />
                    <Input
                        id="image_path"
                        type="file"
                        accept="image/*"
                        @input="formInfo.image_path = ($event.target as HTMLInputElement).files?.[0] ?? null"
                    />
                    <p class="text-[11px] text-muted-foreground">
                        {{ existingImage ? 'Choose a new file to replace, or leave empty to keep.' : 'PNG / JPG up to 30 MB.' }}
                    </p>
                    <InputError :message="usePage().props.errors.image_path" />
                </div>

                <div>
                    <Label>Allegiances</Label>
                    <SearchableMultiselect
                        v-model="formInfo.allegiance_ids"
                        placeholder="Search allegiances…"
                        :options="allegiances"
                        option-value="id"
                    />
                </div>

                <div>
                    <Label>Abilities</Label>
                    <SearchableMultiselect
                        v-model="formInfo.ability_ids"
                        placeholder="Search abilities…"
                        :options="abilities"
                        option-value="id"
                    />
                </div>

                <div>
                    <Label>Actions</Label>
                    <SearchableMultiselect
                        v-model="formInfo.action_ids"
                        placeholder="Search actions…"
                        :options="actionOptions"
                        option-value="id"
                    />
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <Label>Limits</Label>
                        <Button type="button" variant="outline" size="sm" @click="addLimit">
                            <Plus class="mr-1 size-3" /> Add Limit
                        </Button>
                    </div>
                    <div v-for="(limit, idx) in formInfo.limits" :key="idx" class="mb-2 grid gap-2 rounded-md border p-3 md:grid-cols-[140px_140px_1fr_auto]">
                        <Select v-model="limit.limit_type">
                            <SelectTrigger><SelectValue placeholder="Limit" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in limit_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select v-model="limit.parameter_type">
                            <SelectTrigger><SelectValue placeholder="Param type" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in parameter_types" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Input v-model="limit.parameter_value" placeholder="Value (slug / size / location)" />
                        <Button type="button" variant="ghost" size="sm" @click="removeLimit(idx)"><Trash2 class="size-4" /></Button>
                    </div>
                </div>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.assets.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
