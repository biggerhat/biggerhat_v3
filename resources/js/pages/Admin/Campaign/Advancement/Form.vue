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

interface AdvancementRow {
    id: number;
    name: string;
    body: string;
    flip_value: number | null;
    is_always_available: boolean;
    is_black_joker: boolean;
    is_red_joker: boolean;
    modifier_type: string;
    suit: string | null;
    skl_from: number | null;
    skl_to: number | null;
    grants_signature: boolean;
    joker_freechoice: boolean;
    stat_block: Record<string, unknown> | null;
    defensive_ability_type: string | null;
}

const props = defineProps<{
    item?: AdvancementRow | null;
    route_prefix: string;
    display_label: string;
    suit_options: Array<{ name: string; value: string }>;
}>();

const form = ref({
    name: '' as string,
    body: '' as string,
    flip_value: null as number | null,
    is_always_available: false,
    is_black_joker: false,
    is_red_joker: false,
    modifier_type: 'trigger' as string,
    suit: null as string | null,
    skl_from: null as number | null,
    skl_to: null as number | null,
    grants_signature: false,
    joker_freechoice: false,
    stat_block: null as Record<string, unknown> | null,
    defensive_ability_type: null as string | null,
});

const submit = () => {
    if (props.item) router.post(route(`${props.route_prefix}.update`, props.item.id), form.value);
    else router.post(route(`${props.route_prefix}.store`), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head :title="`Campaign — ${display_label} Advancement — Admin`" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ item ? 'Edit' : 'New' }} {{ display_label }} Advancement</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="flip_value">Flip Value (1–13, blank for joker/always)</Label>
                        <Input id="flip_value" type="number" v-model.number="form.flip_value" />
                    </div>
                </div>

                <div>
                    <Label for="body">Body Text</Label>
                    <Textarea id="body" v-model="form.body" rows="3" />
                    <p class="text-[10px] text-muted-foreground">
                        Use rulebook tokens like <code>&#123;&#123;ram&#125;&#125;</code> or <code>&#123;&#123;signatureaction&#125;&#125;</code> —
                        rendered via GameText.
                    </p>
                    <InputError :message="usePage().props.errors.body" />
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <Label for="modifier_type">Modifier Type</Label>
                        <Select v-model="form.modifier_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="trigger">Trigger</SelectItem>
                                <SelectItem value="skl">Skl Boost</SelectItem>
                                <SelectItem value="signature">Signature</SelectItem>
                                <SelectItem value="choice">Choice (action/ability)</SelectItem>
                                <SelectItem value="joker">Joker</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label for="suit">Suit (triggers only)</Label>
                        <Select v-model="form.suit">
                            <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in suit_options" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <fieldset class="grid gap-3 rounded-md border p-3 md:grid-cols-2">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Skl Boost (optional)</legend>
                    <div><Label>From</Label><Input type="number" v-model.number="form.skl_from" /></div>
                    <div><Label>To</Label><Input type="number" v-model.number="form.skl_to" /></div>
                </fieldset>

                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_always_available" @update:checked="(v: boolean) => (form.is_always_available = v)" />
                        <span>Always available (no flip)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.grants_signature" @update:checked="(v: boolean) => (form.grants_signature = v)" />
                        <span>Converts target action to <code>&#123;&#123;signatureaction&#125;&#125;</code></span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_black_joker" @update:checked="(v: boolean) => (form.is_black_joker = v)" />
                        <span>Black Joker entry</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_red_joker" @update:checked="(v: boolean) => (form.is_red_joker = v)" />
                        <span>Red Joker entry</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm sm:col-span-2">
                        <Checkbox :checked="form.joker_freechoice" @update:checked="(v: boolean) => (form.joker_freechoice = v)" />
                        <span>Joker "Choose freely" (action/ability tables — pick anything ≤ cost cap)</span>
                    </label>
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route(`${route_prefix}.index`))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
