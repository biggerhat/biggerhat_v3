<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface StatBlock {
    type?: string | null;
    range?: number | string | null;
    range_type?: string | null;
    stat?: number | string | null;
    resisted_by?: string | null;
    target_number?: number | string | null;
    damage?: number | string | null;
}
interface AdvancementActionRow {
    id: number;
    flip_value: number | null;
    is_joker: boolean;
    is_always_available: boolean;
    talent_name: string;
    effect_text: string;
    action_id: number | null;
    stat_block: StatBlock | null;
}

const props = defineProps<{
    item?: AdvancementActionRow | null;
    actions: Array<{ name: string; value: number }>;
}>();

const form = ref({
    flip_value: null as number | null,
    is_joker: false,
    is_always_available: false,
    talent_name: '' as string,
    effect_text: '' as string,
    action_id: null as number | null,
    stat_block: {
        type: 'tactical',
        range: null,
        range_type: null,
        stat: null,
        resisted_by: null,
        target_number: null,
        damage: null,
    } as StatBlock,
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.advancement-action.update', props.item.id), form.value);
    else router.post(route('admin.campaign.advancement-action.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
    if (!form.value.stat_block) {
        form.value.stat_block = { type: 'tactical', range: null, range_type: null, stat: null, resisted_by: null, target_number: null, damage: null };
    }
});
</script>

<template>
    <Head title="Campaign — Action Advancement — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ item ? 'Edit' : 'New' }} Action Advancement</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="talent_name">Talent Name</Label>
                        <Input id="talent_name" v-model="form.talent_name" />
                        <InputError :message="usePage().props.errors.talent_name" />
                    </div>
                    <div>
                        <Label for="flip_value">Flip Value (1–13, blank for joker/always)</Label>
                        <Input id="flip_value" type="number" min="1" max="13" v-model.number="form.flip_value" />
                    </div>
                </div>

                <div>
                    <Label for="effect_text">Effect Text</Label>
                    <Textarea id="effect_text" v-model="form.effect_text" rows="3" />
                    <InputError :message="usePage().props.errors.effect_text" />
                </div>

                <div>
                    <Label for="action_id">Action Lookup</Label>
                    <Select
                        :model-value="form.action_id?.toString() ?? undefined"
                        @update:model-value="(v) => (form.action_id = v ? Number(v) : null)"
                    >
                        <SelectTrigger><SelectValue placeholder="Bespoke — no lookup" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="opt in actions" :key="opt.value" :value="opt.value.toString()">{{ opt.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-[10px] text-muted-foreground">
                        Set when this row grants an action that already exists on some model's card — the stat block below is ignored.
                    </p>
                </div>

                <fieldset v-if="!form.action_id" class="grid gap-3 rounded-md border p-3 md:grid-cols-3">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Bespoke Stat Block</legend>
                    <div><Label>Type</Label><Input v-model="form.stat_block.type" placeholder="attack / tactical" /></div>
                    <div><Label>Range</Label><Input v-model="form.stat_block.range" /></div>
                    <div><Label>Range Type</Label><Input v-model="form.stat_block.range_type" /></div>
                    <div><Label>Skl</Label><Input v-model="form.stat_block.stat" /></div>
                    <div><Label>Resisted By</Label><Input v-model="form.stat_block.resisted_by" /></div>
                    <div><Label>Target Number</Label><Input v-model="form.stat_block.target_number" /></div>
                    <div><Label>Damage</Label><Input v-model="form.stat_block.damage" /></div>
                </fieldset>

                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_always_available" @update:checked="(v: boolean) => (form.is_always_available = v)" />
                        <span>Always available (no flip)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_joker" @update:checked="(v: boolean) => (form.is_joker = v)" />
                        <span>Any Joker — free pick (cost &lt;= 10, shares keyword)</span>
                    </label>
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.advancement-action.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
