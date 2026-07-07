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
    effect_text: string;
    flip_value: number | null;
    is_black_joker: boolean;
    is_red_joker: boolean;
    is_always_available: boolean;
    modifier_type: string;
    suit: string | null;
    skl_from: number | null;
    skl_from_max: number | null;
    skl_to: number | null;
    trigger_id: number | null;
}

const props = defineProps<{
    item?: AdvancementRow | null;
    route_prefix: string;
    display_label: string;
    suit_options: Array<{ name: string; value: string }>;
    triggers: Array<{ name: string; value: number }>;
}>();

const form = ref({
    name: '' as string,
    effect_text: '' as string,
    flip_value: null as number | null,
    is_black_joker: false,
    is_red_joker: false,
    is_always_available: false,
    modifier_type: 'trigger' as string,
    suit: null as string | null,
    skl_from: null as number | null,
    skl_from_max: null as number | null,
    skl_to: null as number | null,
    trigger_id: null as number | null,
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
                        <Input id="flip_value" type="number" min="1" max="13" v-model.number="form.flip_value" />
                    </div>
                </div>

                <div>
                    <Label for="effect_text">Effect Text</Label>
                    <Textarea id="effect_text" v-model="form.effect_text" rows="3" />
                    <p class="text-[10px] text-muted-foreground">
                        Use rulebook tokens like <code>&#123;&#123;ram&#125;&#125;</code> or <code>&#123;&#123;signatureaction&#125;&#125;</code> —
                        rendered via GameText.
                    </p>
                    <InputError :message="usePage().props.errors.effect_text" />
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <Label for="modifier_type">Type</Label>
                        <Select v-model="form.modifier_type">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="trigger">Trigger</SelectItem>
                                <SelectItem value="skl_boost">Skl Boost</SelectItem>
                                <SelectItem value="signature">Signature</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label for="suit">Suit (trigger rows only)</Label>
                        <Select v-model="form.suit">
                            <SelectTrigger><SelectValue placeholder="—" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in suit_options" :key="opt.value" :value="opt.value">{{ opt.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div>
                        <Label for="trigger_id">Trigger Lookup</Label>
                        <Select
                            :model-value="form.trigger_id?.toString() ?? undefined"
                            @update:model-value="(v) => (form.trigger_id = v ? Number(v) : null)"
                        >
                            <SelectTrigger><SelectValue placeholder="Bespoke — no lookup" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="opt in triggers" :key="opt.value" :value="opt.value.toString()">{{ opt.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-[10px] text-muted-foreground">Set only when this row grants an existing trigger from the game.</p>
                    </div>
                </div>

                <fieldset v-if="form.modifier_type === 'skl_boost'" class="grid gap-3 rounded-md border p-3 md:grid-cols-3">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Skl Boost</legend>
                    <div>
                        <Label>From (min)</Label>
                        <Input type="number" v-model.number="form.skl_from" />
                    </div>
                    <div>
                        <Label>From (max, optional)</Label>
                        <Input type="number" v-model.number="form.skl_from_max" placeholder="Same as min" />
                        <InputError :message="usePage().props.errors.skl_from_max" />
                    </div>
                    <div><Label>To</Label><Input type="number" v-model.number="form.skl_to" /></div>
                    <p class="col-span-full text-[10px] text-muted-foreground">
                        Leave "max" blank for a single required Skl (e.g. "Skl of 4"). Set it for a qualifying range (e.g. "Skl of 0 or 1" is min 0,
                        max 1).
                    </p>
                </fieldset>

                <div class="grid gap-2 sm:grid-cols-3">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_always_available" @update:checked="(v: boolean) => (form.is_always_available = v)" />
                        <span>Always available (no flip)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_black_joker" @update:checked="(v: boolean) => (form.is_black_joker = v)" />
                        <span>Black Joker entry</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_red_joker" @update:checked="(v: boolean) => (form.is_red_joker = v)" />
                        <span>Red Joker entry</span>
                    </label>
                </div>
                <p class="text-[10px] text-muted-foreground">
                    Check <strong>one</strong> color for a card-specific entry (e.g. Tactical Mod's Red/Black-specific triggers). Check
                    <strong>both</strong> for an Any-Joker entry — either color qualifies (e.g. Attack Mod's Cruel Lessons/Consult the Bones).
                </p>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route(`${route_prefix}.index`))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
