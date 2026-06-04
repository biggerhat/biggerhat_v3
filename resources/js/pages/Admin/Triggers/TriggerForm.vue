<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    trigger: {
        type: [Object, Array],
        required: false,
        default() {
            return null;
        },
    },
    suits: {
        type: [Object, Array],
        required: false,
        default() {
            return [];
        },
    },
    game_mode_types: {
        type: Array as () => { value: string; name: string }[],
        required: false,
        default() {
            return [];
        },
    },
});

const formInfo = ref({
    name: null,
    suits: null,
    stone_cost: 0,
    description: null,
    game_mode_type: 'standard',
    // Campaign-only fields — surfaced when game_mode_type === 'campaign'.
    campaign_advancement_kind: null as string | null, // 'attack' | 'tactical'
    campaign_flip_value: null as number | null,
    campaign_is_always_available: false,
    campaign_joker_freechoice: false,
    campaign_grants_signature: false,
    campaign_modifier_type: null as string | null, // 'trigger' | 'skl' | 'signature' | 'joker'
    campaign_skl_from: null as number | null,
    campaign_skl_to: null as number | null,
});

const submit = () => {
    router.post(props.trigger ? route('admin.triggers.update', props.trigger.slug) : route('admin.triggers.store'), formInfo.value);
};

onMounted(() => {
    formInfo.value.name = props.trigger?.name ?? null;
    formInfo.value.stone_cost = props.trigger?.stone_cost ?? 0;
    formInfo.value.suits = props.trigger?.suits ?? null;
    formInfo.value.description = props.trigger?.description ?? null;
    formInfo.value.game_mode_type = props.trigger?.game_mode_type ?? 'standard';
    // Campaign-only — hydrate from the trigger if present.
    formInfo.value.campaign_advancement_kind = props.trigger?.campaign_advancement_kind ?? null;
    formInfo.value.campaign_flip_value = props.trigger?.campaign_flip_value ?? null;
    formInfo.value.campaign_is_always_available = props.trigger?.campaign_is_always_available ?? false;
    formInfo.value.campaign_joker_freechoice = props.trigger?.campaign_joker_freechoice ?? false;
    formInfo.value.campaign_grants_signature = props.trigger?.campaign_grants_signature ?? false;
    formInfo.value.campaign_modifier_type = props.trigger?.campaign_modifier_type ?? null;
    formInfo.value.campaign_skl_from = props.trigger?.campaign_skl_from ?? null;
    formInfo.value.campaign_skl_to = props.trigger?.campaign_skl_to ?? null;
});
</script>

<template>
    <Head title="Triggers - Admin" />
    <div class="container mx-auto mt-6">
        <Card>
            <CardHeader>
                <CardTitle>Trigger</CardTitle>
                <CardDescription>Create and Edit Trigger Information</CardDescription>
            </CardHeader>
            <CardContent>
                <form>
                    <div class="grid w-full items-center gap-4">
                        <div class="flex flex-col space-y-1.5">
                            <Label for="game_mode_type">Game Mode</Label>
                            <Select id="game_mode_type" v-model="formInfo.game_mode_type">
                                <SelectTrigger>
                                    <SelectValue placeholder="Game Mode Type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="mode in props.game_mode_types" :value="mode.value" :key="mode.value">
                                        {{ mode.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="usePage().props.errors.game_mode_type" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <Label for="name">Name</Label>
                            <Input id="name" autofocus v-model="formInfo.name" placeholder="Trigger Name" />
                            <InputError :message="usePage().props.errors.name" />
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="grid auto-rows-min gap-4 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="suits">Required Suits</Label>
                                    <Input id="suits" v-model="formInfo.suits" placeholder="Required Suits" />
                                    <InputError :message="usePage().props.errors.suits" />
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="stone_cost">Stone Cost</Label>
                                    <Input id="stone_cost" v-model="formInfo.stone_cost" type="number" min="0" placeholder="0" />
                                    <InputError :message="usePage().props.errors.stone_cost" />
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-1.5">
                            <div class="flex flex-col space-y-1.5">
                                <Label for="description">Trigger Text</Label>
                                <Textarea id="description" v-model="formInfo.description" placeholder="Type the trigger text here." />
                                <InputError :message="usePage().props.errors.description" />
                            </div>
                        </div>

                        <!-- Campaign-only fields — surfaced when Campaign Mode is active. -->
                        <fieldset
                            v-if="formInfo.game_mode_type === 'campaign'"
                            class="space-y-3 rounded-md border border-primary/30 bg-primary/5 p-3"
                        >
                            <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Campaign Mode — Advancement</legend>
                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="campaign_advancement_kind">Advancement Kind</Label>
                                    <Select id="campaign_advancement_kind" v-model="formInfo.campaign_advancement_kind">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Attack or Tactical" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="attack">Attack-Mod</SelectItem>
                                            <SelectItem value="tactical">Tactical-Mod</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="campaign_modifier_type">Modifier Type</Label>
                                    <Select id="campaign_modifier_type" v-model="formInfo.campaign_modifier_type">
                                        <SelectTrigger>
                                            <SelectValue placeholder="trigger / skl / signature" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="trigger">New Trigger</SelectItem>
                                            <SelectItem value="skl">Skl Boost</SelectItem>
                                            <SelectItem value="signature">Signature Conversion</SelectItem>
                                            <SelectItem value="joker">Joker (free choice)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="flex flex-col space-y-1.5">
                                    <Label for="campaign_flip_value">Flip Value (1–13)</Label>
                                    <Input id="campaign_flip_value" type="number" min="1" max="13" v-model.number="formInfo.campaign_flip_value" />
                                    <p class="text-[11px] text-muted-foreground">Leave blank for Always-Available.</p>
                                </div>
                                <div v-if="formInfo.campaign_modifier_type === 'skl'" class="flex flex-col space-y-1.5">
                                    <Label>Skl Boost (from → to)</Label>
                                    <div class="flex gap-2">
                                        <Input type="number" min="1" v-model.number="formInfo.campaign_skl_from" placeholder="from" class="w-24" />
                                        <Input type="number" min="1" v-model.number="formInfo.campaign_skl_to" placeholder="to" class="w-24" />
                                    </div>
                                </div>
                            </div>
                            <div class="grid gap-2 md:grid-cols-3">
                                <label class="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        :checked="formInfo.campaign_is_always_available"
                                        @update:checked="(v: boolean) => (formInfo.campaign_is_always_available = v)"
                                    />
                                    <span>Always available</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        :checked="formInfo.campaign_joker_freechoice"
                                        @update:checked="(v: boolean) => (formInfo.campaign_joker_freechoice = v)"
                                    />
                                    <span>Joker "Choose freely"</span>
                                </label>
                                <label class="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        :checked="formInfo.campaign_grants_signature"
                                        @update:checked="(v: boolean) => (formInfo.campaign_grants_signature = v)"
                                    />
                                    <span>Grants signature</span>
                                </label>
                            </div>
                        </fieldset>
                    </div>
                </form>
            </CardContent>
            <CardFooter class="flex justify-between px-6 pb-6">
                <Button @click="router.get(route('admin.triggers.index'))" variant="outline"> Cancel </Button>
                <Button @click="submit">Save</Button>
            </CardFooter>
        </Card>
    </div>
</template>
