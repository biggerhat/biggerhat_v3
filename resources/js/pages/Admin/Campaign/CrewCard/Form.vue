<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, usePage } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';

interface ActionOption {
    id: number;
    name: string;
    type: string | null;
}

interface AbilityOption {
    id: number;
    name: string;
}

interface SelectedAction {
    id: string;
    name: string;
    type: string | null;
    is_signature: boolean;
}

interface SelectedAbility {
    id: string;
    name: string;
}

interface CrewCardRow {
    id: number;
    name: string;
    description: string | null;
    requires_token_choice: boolean;
    requires_marker_choice: boolean;
    requires_upgrade_type_choice: boolean;
    actions: { id: number; name: string; is_signature: boolean }[];
    abilities: { id: number; name: string }[];
}

const props = defineProps<{
    item?: CrewCardRow | null;
    all_actions: ActionOption[];
    all_abilities: AbilityOption[];
}>();

const form = ref({
    name: '',
    description: null as string | null,
    requires_token_choice: false,
    requires_marker_choice: false,
    requires_upgrade_type_choice: false,
});

// Actions are managed as a list of {id, name, type, is_signature, borrow_exclusion}
// objects so each entry can carry its own pivot data.
const selectedActions = ref<SelectedAction[]>([]);

// Action search — filter all_actions by name, excluding already-selected ids.
const actionSearch = ref('');
const filteredActionOptions = computed(() => {
    const q = actionSearch.value.toLowerCase().trim();
    const selectedIds = new Set(selectedActions.value.map((a) => a.id));
    return props.all_actions.filter((a) => !selectedIds.has(String(a.id)) && (!q || a.name.toLowerCase().includes(q)));
});

const addAction = (opt: ActionOption) => {
    selectedActions.value.push({ id: String(opt.id), name: opt.name, type: opt.type, is_signature: false });
    actionSearch.value = '';
};

const removeAction = (idx: number) => selectedActions.value.splice(idx, 1);

// Abilities: same inline list pattern as actions, minus the type/signature fields.
const selectedAbilities = ref<SelectedAbility[]>([]);

const abilitySearch = ref('');
const filteredAbilityOptions = computed(() => {
    const q = abilitySearch.value.toLowerCase().trim();
    const selectedIds = new Set(selectedAbilities.value.map((a) => a.id));
    return props.all_abilities.filter((a) => !selectedIds.has(String(a.id)) && (!q || a.name.toLowerCase().includes(q)));
});

const addAbility = (opt: AbilityOption) => {
    selectedAbilities.value.push({ id: String(opt.id), name: opt.name });
    abilitySearch.value = '';
};

const removeAbility = (idx: number) => selectedAbilities.value.splice(idx, 1);

const submit = () => {
    const payload = {
        ...form.value,
        actions: selectedActions.value.map((a) => ({ id: Number(a.id), is_signature: a.is_signature })),
        abilities: selectedAbilities.value.map((a) => ({ id: Number(a.id) })),
    };
    if (props.item) router.post(route('admin.campaign.crew-cards.update', props.item.id), payload);
    else router.post(route('admin.campaign.crew-cards.store'), payload);
};

onMounted(() => {
    if (!props.item) return;
    form.value.name = props.item.name;
    form.value.description = props.item.description;
    form.value.requires_token_choice = props.item.requires_token_choice;
    form.value.requires_marker_choice = props.item.requires_marker_choice;
    form.value.requires_upgrade_type_choice = props.item.requires_upgrade_type_choice;

    // Pre-populate selected actions/abilities from existing pivot data.
    const actionLookup = new Map(props.all_actions.map((a) => [a.id, a]));
    selectedActions.value = props.item.actions.map((a) => ({
        id: String(a.id),
        name: a.name,
        type: actionLookup.get(a.id)?.type ?? null,
        is_signature: a.is_signature,
    }));
    selectedAbilities.value = props.item.abilities.map((a) => ({
        id: String(a.id),
        name: a.name,
    }));
});
</script>

<template>
    <Head title="Crew Card — Admin" />
    <div class="container mx-auto mt-6 px-4 pb-6">
        <Card>
            <CardHeader>
                <CardTitle>{{ item ? 'Edit' : 'New' }} Crew Card</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div>
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="usePage().props.errors.name" />
                </div>
                <div>
                    <Label for="description">Description</Label>
                    <Textarea id="description" v-model="form.description" rows="5" placeholder="The rule text that appears on the card..." />
                    <InputError :message="usePage().props.errors.description" />
                </div>
                <!-- Actions: inline list with per-action signature checkbox -->
                <div>
                    <Label>Linked Actions</Label>
                    <div class="mt-1 space-y-1">
                        <EmptyState v-if="selectedActions.length === 0" compact title="No actions linked yet" description="" />
                        <div v-for="(action, idx) in selectedActions" :key="action.id + '-' + idx" class="flex items-center gap-2 rounded-md border px-2 py-1.5">
                            <span class="flex-1 truncate text-sm font-medium">{{ action.name }}</span>
                            <Badge v-if="action.type" variant="secondary" class="shrink-0 px-1 py-0 text-[10px]">{{ action.type }}</Badge>
                            <label class="flex shrink-0 cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                <Checkbox :checked="action.is_signature" @update:checked="(v: boolean) => (action.is_signature = v)" />
                                Signature
                            </label>
                            <button class="shrink-0 text-muted-foreground hover:text-destructive" @click="removeAction(idx)">
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <!-- Search to add more actions -->
                    <div class="relative mt-2">
                        <Input v-model="actionSearch" placeholder="Search actions to add..." class="h-8 text-sm" />
                        <div
                            v-if="actionSearch.length > 0 && filteredActionOptions.length"
                            class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border bg-popover p-1 shadow-md"
                        >
                            <button
                                v-for="opt in filteredActionOptions"
                                :key="opt.id"
                                class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                @click="addAction(opt)"
                            >
                                <span class="flex-1 truncate">{{ opt.name }}</span>
                                <Badge v-if="opt.type" variant="secondary" class="shrink-0 px-1 py-0 text-[10px]">{{ opt.type }}</Badge>
                            </button>
                            <p v-if="filteredActionOptions.length === 0" class="px-2 py-1.5 text-xs text-muted-foreground">No matches.</p>
                        </div>
                    </div>
                    <InputError :message="usePage().props.errors['actions']" />
                </div>

                <!-- Abilities: same inline list pattern as Actions -->
                <div>
                    <Label>Linked Abilities</Label>
                    <div class="mt-1 space-y-1">
                        <EmptyState v-if="selectedAbilities.length === 0" compact title="No abilities linked yet" description="" />
                        <div v-for="(ability, idx) in selectedAbilities" :key="ability.id + '-' + idx" class="flex items-center gap-2 rounded-md border px-2 py-1.5">
                            <span class="flex-1 truncate text-sm font-medium">{{ ability.name }}</span>
                            <button class="shrink-0 text-muted-foreground hover:text-destructive" @click="removeAbility(idx)">
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>
                    <!-- Search to add more abilities -->
                    <div class="relative mt-2">
                        <Input v-model="abilitySearch" placeholder="Search abilities to add..." class="h-8 text-sm" />
                        <div
                            v-if="abilitySearch.length > 0 && filteredAbilityOptions.length"
                            class="absolute z-10 mt-1 max-h-48 w-full overflow-y-auto rounded-md border bg-popover p-1 shadow-md"
                        >
                            <button
                                v-for="opt in filteredAbilityOptions"
                                :key="opt.id"
                                class="flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-accent"
                                @click="addAbility(opt)"
                            >
                                <span class="flex-1 truncate">{{ opt.name }}</span>
                            </button>
                            <p v-if="filteredAbilityOptions.length === 0" class="px-2 py-1.5 text-xs text-muted-foreground">No matches.</p>
                        </div>
                    </div>
                    <InputError :message="usePage().props.errors['abilities']" />
                </div>

                <div class="space-y-2">
                    <p class="text-sm font-medium">Choice requirements</p>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.requires_token_choice" @update:checked="(v: boolean) => (form.requires_token_choice = v)" />
                        <span>Requires player to choose a token type</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox :checked="form.requires_marker_choice" @update:checked="(v: boolean) => (form.requires_marker_choice = v)" />
                        <span>Requires player to choose a marker type</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <Checkbox
                            :checked="form.requires_upgrade_type_choice"
                            @update:checked="(v: boolean) => (form.requires_upgrade_type_choice = v)"
                        />
                        <span>Requires player to choose an upgrade type</span>
                    </label>
                </div>
            </CardContent>
            <CardFooter class="justify-end gap-2">
                <Button variant="outline" @click="router.get(route('admin.campaign.crew-cards.index'))">Cancel</Button>
                <Button @click="submit">{{ item ? 'Update' : 'Create' }}</Button>
            </CardFooter>
        </Card>
    </div>
</template>
