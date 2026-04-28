<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchableMultiselect from '@/components/SearchableMultiselect.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { TosSelectOption } from '@/types/tos';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface TriggerRow {
    id: number;
    name: string;
    suits: string | null;
    margin_cost: number | null;
    timing: string;
    body: string | null;
    actions: Array<{ id: number }>;
}

const props = defineProps<{
    trigger?: TriggerRow | null;
    actions: Array<{ id: number; name: string }>;
    timings: TosSelectOption[];
}>();

type CostType = 'suits' | 'margin' | 'none';

const costType = ref<CostType>('suits');

const formInfo = ref({
    action_ids: [] as string[],
    name: '' as string,
    suits: null as string | null,
    margin_cost: null as number | null,
    timing: 'default' as string,
    body: null as string | null,
});

function setCostType(next: CostType) {
    costType.value = next;
    if (next !== 'suits') formInfo.value.suits = null;
    if (next !== 'margin') formInfo.value.margin_cost = null;
}

const submit = () => {
    const payload = {
        ...formInfo.value,
        action_ids: formInfo.value.action_ids.map((v) => Number.parseInt(v, 10)),
    };
    if (props.trigger) router.post(route('admin.tos.triggers.update', props.trigger.id), payload);
    else router.post(route('admin.tos.triggers.store'), payload);
};

onMounted(() => {
    if (!props.trigger) return;
    Object.assign(formInfo.value, {
        action_ids: (props.trigger.actions ?? []).map((a) => String(a.id)),
        name: props.trigger.name,
        suits: props.trigger.suits,
        margin_cost: props.trigger.margin_cost,
        timing: props.trigger.timing,
        body: props.trigger.body,
    });
    if (props.trigger.suits) costType.value = 'suits';
    else if (props.trigger.margin_cost !== null) costType.value = 'margin';
    else costType.value = 'none';
});
</script>

<template>
    <Head title="TOS Trigger — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader><CardTitle>{{ trigger ? 'Edit Trigger' : 'New Trigger' }}</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div>
                    <Label for="action_ids">Actions <span class="ml-1 text-[10px] font-normal text-muted-foreground">(optional — leave empty to save unattached, or pick one or more)</span></Label>
                    <SearchableMultiselect
                        v-model="formInfo.action_ids"
                        placeholder="Search actions…"
                        :options="actions"
                        option-value="id"
                    />
                    <InputError :message="usePage().props.errors.action_ids" />
                </div>
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="formInfo.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>

                <div>
                    <Label>Cost type</Label>
                    <div class="mt-1 inline-flex items-center rounded-md border border-input bg-background/60 p-0.5 text-xs font-medium" role="group" aria-label="Trigger cost type">
                        <button
                            type="button"
                            class="inline-flex h-7 items-center rounded px-3 transition-colors"
                            :class="costType === 'suits' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                            @click="setCostType('suits')"
                        >
                            Suits
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-7 items-center rounded px-3 transition-colors"
                            :class="costType === 'margin' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                            @click="setCostType('margin')"
                        >
                            Margin
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-7 items-center rounded px-3 transition-colors"
                            :class="costType === 'none' ? 'bg-primary text-primary-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                            @click="setCostType('none')"
                        >
                            None
                        </button>
                    </div>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        A Trigger is either suit-driven (e.g. <span class="font-mono">R</span>) or margin-driven (numeric margin cost), never both.
                    </p>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div v-if="costType === 'suits'">
                        <Label for="suits">Suits</Label>
                        <Input id="suits" v-model="formInfo.suits" placeholder="R / M / C / T" />
                        <InputError :message="usePage().props.errors.suits" />
                    </div>
                    <div v-else-if="costType === 'margin'">
                        <Label for="margin_cost">Margin Cost</Label>
                        <Input id="margin_cost" v-model.number="formInfo.margin_cost" type="number" min="0" placeholder="e.g. 5" />
                        <InputError :message="usePage().props.errors.margin_cost" />
                    </div>
                    <div>
                        <Label for="timing">Timing</Label>
                        <Select v-model="formInfo.timing">
                            <SelectTrigger><SelectValue placeholder="Timing" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in timings" :key="t.value" :value="t.value">{{ t.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div>
                    <Label for="body">Body</Label>
                    <Textarea id="body" v-model="formInfo.body" />
                </div>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button variant="outline" @click="router.get(route('admin.tos.triggers.index'))">Cancel</Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
