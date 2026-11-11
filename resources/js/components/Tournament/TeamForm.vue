<script setup lang="ts">
import DeclarationFields from '@/components/Tournament/DeclarationFields.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { TeamMemberDraft } from '@/types/tournament';

/**
 * The people on one team: a name, an optional linked account, and each person's own declaration
 * (faction, or allegiance + Garrison). The same editor is used to add a team and to edit one.
 */
defineProps<{
    declaration: string;
    factions: Record<string, { name: string; logo: string }>;
    allegiances: { id: number; slug: string; name: string }[];
    /** Accounts (typically RSVPs) not yet playing, offered as a link for each member. */
    availableUsers: { id: number; name: string }[];
}>();

const members = defineModel<TeamMemberDraft[]>({ required: true });

const NONE = '__none__';
const optionsFor = (m: TeamMemberDraft, available: { id: number; name: string }[]) =>
    m.user_id !== null && !available.some((u) => u.id === m.user_id)
        ? [{ id: m.user_id, name: m.user_name ?? `Account #${m.user_id}` }, ...available]
        : available;

const setUser = (m: TeamMemberDraft, value: unknown, available: { id: number; name: string }[]) => {
    const id = value === NONE || value === undefined || value === null ? null : Number(value);
    m.user_id = id;
    m.user_name = id === null ? null : (optionsFor({ ...m, user_id: id }, available).find((u) => u.id === id)?.name ?? null);
    // Saves typing: a linked account names the member until they're renamed.
    if (id !== null && !m.display_name.trim()) m.display_name = m.user_name ?? '';
};
</script>

<template>
    <div class="space-y-3">
        <fieldset v-for="(m, i) in members" :key="m.id ?? `new-${i}`" class="space-y-2 rounded-md border p-3">
            <legend class="px-1 text-xs font-semibold text-muted-foreground">Member {{ i + 1 }}</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                <div class="space-y-1">
                    <Label :for="`team-member-${i}-name`" class="text-xs">Name</Label>
                    <Input :id="`team-member-${i}-name`" v-model="m.display_name" placeholder="Player name" class="h-9 text-sm" autocomplete="off" />
                </div>
                <div class="space-y-1">
                    <Label class="text-xs">BiggerHat account <span class="text-muted-foreground">(optional)</span></Label>
                    <Select :model-value="m.user_id === null ? NONE : String(m.user_id)" @update:model-value="(v) => setUser(m, v, availableUsers)">
                        <SelectTrigger class="h-9 w-full text-xs"><SelectValue placeholder="No account" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">No account</SelectItem>
                            <SelectItem v-for="u in optionsFor(m, availableUsers)" :key="u.id" :value="String(u.id)">{{ u.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                <DeclarationFields
                    v-model:faction="m.faction"
                    v-model:allegiance="m.allegiance"
                    v-model:garrison-code="m.garrison_code"
                    :declaration="declaration"
                    :factions="factions"
                    :allegiances="allegiances"
                />
            </div>
        </fieldset>
    </div>
</template>
