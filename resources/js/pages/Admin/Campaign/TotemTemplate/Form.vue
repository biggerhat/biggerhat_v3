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

interface FactionOption {
    value: string;
    name: string;
}

interface TotemTemplateRow {
    id: number;
    name: string;
    title: string | null;
    faction: string;
    station: string | null;
    cost: number | null;
    health: number;
    defense: number;
    defense_suit: string | null;
    willpower: number;
    willpower_suit: string | null;
    speed: number;
    size: number | null;
    base: string;
    campaign_totem_flip_value: number | null;
    campaign_is_black_joker_totem: boolean;
    campaign_is_red_joker_totem: boolean;
    campaign_totem_special_replace: boolean;
    notes: string | null;
}

const props = defineProps<{
    item?: TotemTemplateRow | null;
    factions: FactionOption[];
}>();

const form = ref({
    name: '',
    title: null as string | null,
    faction: '',
    station: null as string | null,
    cost: null as number | null,
    health: 4,
    defense: 4,
    defense_suit: null as string | null,
    willpower: 4,
    willpower_suit: null as string | null,
    speed: 5,
    size: null as number | null,
    base: '30',
    campaign_totem_flip_value: null as number | null,
    campaign_is_black_joker_totem: false,
    campaign_is_red_joker_totem: false,
    campaign_totem_special_replace: false,
    notes: null as string | null,
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.totem-templates.update', props.item.id), form.value);
    else router.post(route('admin.campaign.totem-templates.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, {
        name: props.item.name,
        title: props.item.title,
        faction: props.item.faction,
        station: props.item.station,
        cost: props.item.cost,
        health: props.item.health,
        defense: props.item.defense,
        defense_suit: props.item.defense_suit,
        willpower: props.item.willpower,
        willpower_suit: props.item.willpower_suit,
        speed: props.item.speed,
        size: props.item.size,
        base: props.item.base,
        campaign_totem_flip_value: props.item.campaign_totem_flip_value,
        campaign_is_black_joker_totem: props.item.campaign_is_black_joker_totem,
        campaign_is_red_joker_totem: props.item.campaign_is_red_joker_totem,
        campaign_totem_special_replace: props.item.campaign_totem_special_replace,
        notes: props.item.notes,
    });
});
</script>

<template>
    <Head title="Totem Template — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ item ? 'Edit' : 'New' }} Totem Template</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <!-- Basic identity -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" placeholder="e.g. Wicked Doll" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="title">Title (optional)</Label>
                        <Input id="title" v-model="form.title" placeholder="e.g. Collector's Companion" />
                        <InputError :message="usePage().props.errors.title" />
                    </div>
                    <div>
                        <Label for="faction">Faction</Label>
                        <Select id="faction" v-model="form.faction">
                            <SelectTrigger><SelectValue placeholder="Select faction" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="f in factions" :key="f.value" :value="f.value">{{ f.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="usePage().props.errors.faction" />
                    </div>
                    <div>
                        <Label for="station">Station (optional)</Label>
                        <Input id="station" v-model="form.station" placeholder="e.g. Totem" />
                        <InputError :message="usePage().props.errors.station" />
                    </div>
                </div>

                <!-- Stats -->
                <div>
                    <p class="mb-2 text-sm font-semibold">Card Stats</p>
                    <div class="grid gap-3 md:grid-cols-4">
                        <div>
                            <Label for="cost">Cost (ss)</Label>
                            <Input id="cost" v-model.number="form.cost" type="number" min="0" max="20" />
                            <InputError :message="usePage().props.errors.cost" />
                        </div>
                        <div>
                            <Label for="health">Health</Label>
                            <Input id="health" v-model.number="form.health" type="number" min="1" max="30" />
                            <InputError :message="usePage().props.errors.health" />
                        </div>
                        <div>
                            <Label for="speed">Speed</Label>
                            <Input id="speed" v-model.number="form.speed" type="number" min="1" max="10" />
                            <InputError :message="usePage().props.errors.speed" />
                        </div>
                        <div>
                            <Label for="size">Size</Label>
                            <Input id="size" v-model.number="form.size" type="number" min="1" max="5" />
                            <InputError :message="usePage().props.errors.size" />
                        </div>
                        <div>
                            <Label for="defense">Defense</Label>
                            <Input id="defense" v-model.number="form.defense" type="number" min="1" max="10" />
                            <InputError :message="usePage().props.errors.defense" />
                        </div>
                        <div>
                            <Label for="defense_suit">Defense Suit</Label>
                            <Input id="defense_suit" v-model="form.defense_suit" placeholder="ram / crow / mask / tome" />
                            <InputError :message="usePage().props.errors.defense_suit" />
                        </div>
                        <div>
                            <Label for="willpower">Willpower</Label>
                            <Input id="willpower" v-model.number="form.willpower" type="number" min="1" max="10" />
                            <InputError :message="usePage().props.errors.willpower" />
                        </div>
                        <div>
                            <Label for="willpower_suit">Willpower Suit</Label>
                            <Input id="willpower_suit" v-model="form.willpower_suit" placeholder="ram / crow / mask / tome" />
                            <InputError :message="usePage().props.errors.willpower_suit" />
                        </div>
                        <div>
                            <Label for="base">Base (mm)</Label>
                            <Input id="base" v-model="form.base" placeholder="30 / 40 / 50" />
                            <InputError :message="usePage().props.errors.base" />
                        </div>
                    </div>
                </div>

                <!-- Campaign metadata -->
                <fieldset class="space-y-3 rounded-md border border-primary/30 bg-primary/5 p-3">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Campaign Totem Metadata</legend>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <Label for="campaign_totem_flip_value">Flip Value (1–13, leave blank for joker entries)</Label>
                            <Input
                                id="campaign_totem_flip_value"
                                v-model.number="form.campaign_totem_flip_value"
                                type="number"
                                min="1"
                                max="13"
                                placeholder="1–13"
                            />
                            <InputError :message="usePage().props.errors.campaign_totem_flip_value" />
                        </div>
                    </div>

                    <div class="grid gap-2 md:grid-cols-3">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :checked="form.campaign_is_black_joker_totem"
                                @update:checked="(v: boolean) => (form.campaign_is_black_joker_totem = v)"
                            />
                            <span>Black Joker result</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :checked="form.campaign_is_red_joker_totem"
                                @update:checked="(v: boolean) => (form.campaign_is_red_joker_totem = v)"
                            />
                            <span>Red Joker result</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :checked="form.campaign_totem_special_replace"
                                @update:checked="(v: boolean) => (form.campaign_totem_special_replace = v)"
                            />
                            <span>Special Replace (replaces rather than adds)</span>
                        </label>
                    </div>
                </fieldset>

                <!-- Notes -->
                <div>
                    <Label for="notes">Internal Notes (optional)</Label>
                    <Textarea id="notes" v-model="form.notes" rows="3" placeholder="Admin-only notes about this totem..." />
                    <InputError :message="usePage().props.errors.notes" />
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.totem-templates.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
