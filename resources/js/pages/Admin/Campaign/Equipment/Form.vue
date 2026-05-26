<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

interface EquipmentRow {
    id: number;
    name: string;
    br: number | null;
    cc: number;
    is_always_available: boolean;
    is_red_joker_entry: boolean;
    ttw_only: boolean;
    is_omens_mark: boolean;
    pool_suit_a: string | null;
    pool_suit_b: string | null;
    is_unique: boolean;
    leader_only: boolean;
    non_unique_only: boolean;
    annihilate_after_game: boolean;
    body: string;
    granted_ability: Record<string, unknown> | null;
    granted_action: Record<string, unknown> | null;
}

const props = defineProps<{ item?: EquipmentRow | null }>();

const form = ref({
    name: '' as string,
    br: null as number | null,
    cc: 1,
    is_always_available: false,
    is_red_joker_entry: false,
    ttw_only: false,
    is_omens_mark: false,
    pool_suit_a: 'ram' as string | null,
    pool_suit_b: 'crow' as string | null,
    is_unique: false,
    leader_only: false,
    non_unique_only: false,
    annihilate_after_game: false,
    body: '' as string,
    granted_ability: null as Record<string, unknown> | null,
    granted_action: null as Record<string, unknown> | null,
});

const submit = () => {
    if (props.item) router.post(route('admin.campaign.equipment.update', props.item.id), form.value);
    else router.post(route('admin.campaign.equipment.store'), form.value);
};

onMounted(() => {
    if (!props.item) return;
    Object.assign(form.value, props.item);
});
</script>

<template>
    <Head title="Equipment — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ item ? 'Edit Equipment' : 'New Equipment' }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <Label for="name">Name</Label>
                        <Input id="name" v-model="form.name" />
                        <InputError :message="usePage().props.errors.name" />
                    </div>
                    <div>
                        <Label for="br">BR (1–13, blank = always)</Label>
                        <Input id="br" type="number" v-model.number="form.br" />
                    </div>
                    <div>
                        <Label for="cc">CC (scrip cost)</Label>
                        <Input id="cc" type="number" v-model.number="form.cc" />
                    </div>
                </div>

                <div>
                    <Label for="body">Body Text</Label>
                    <Textarea id="body" v-model="form.body" rows="4" />
                    <p class="text-[10px] text-muted-foreground">
                        Tokens like <code>&#123;&#123;ram&#125;&#125;</code> / <code>&#123;&#123;melee&#125;&#125;</code> /
                        <code>&#123;&#123;soulstone&#125;&#125;</code> rendered via GameText.
                    </p>
                    <InputError :message="usePage().props.errors.body" />
                </div>

                <fieldset class="grid gap-3 rounded-md border p-3 md:grid-cols-2">
                    <legend class="px-1 text-xs font-medium uppercase text-muted-foreground">Suit Pool Eligibility</legend>
                    <div>
                        <Label>Suit A (e.g. ram)</Label>
                        <Input v-model="form.pool_suit_a" />
                    </div>
                    <div>
                        <Label>Suit B (e.g. crow)</Label>
                        <Input v-model="form.pool_suit_b" />
                    </div>
                </fieldset>

                <div class="grid gap-2 md:grid-cols-2">
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_always_available" @update:checked="(v: boolean) => (form.is_always_available = v)" />
                        <span>Always available (no flip needed)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_red_joker_entry" @update:checked="(v: boolean) => (form.is_red_joker_entry = v)" />
                        <span>Red Joker entry (Those Who Thirst gate)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.ttw_only" @update:checked="(v: boolean) => (form.ttw_only = v)" />
                        <span>Those Who Thirst (post-red-joker sub-table)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_omens_mark" @update:checked="(v: boolean) => (form.is_omens_mark = v)" />
                        <span>Omen's Mark (mandatory attach)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.is_unique" @update:checked="(v: boolean) => (form.is_unique = v)" />
                        <span>Unique (one per arsenal)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.leader_only" @update:checked="(v: boolean) => (form.leader_only = v)" />
                        <span>Leader-only</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.non_unique_only" @update:checked="(v: boolean) => (form.non_unique_only = v)" />
                        <span>Non-unique models only (e.g. Strange Seed Pod)</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.annihilate_after_game" @update:checked="(v: boolean) => (form.annihilate_after_game = v)" />
                        <span>Annihilates after the game (Loot Their Stash etc.)</span>
                    </label>
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.equipment.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
