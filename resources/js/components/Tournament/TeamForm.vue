<script setup lang="ts">
import DeclarationFields from '@/components/Tournament/DeclarationFields.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { TeamMemberDraft } from '@/types/tournament';
import { reactive } from 'vue';

/**
 * The people on one team: a name, an optional linked account, and each person's own declaration
 * (faction, or allegiance + Garrison). The same editor is used to add a team and to edit one.
 */
const props = defineProps<{
    declaration: string;
    factions: Record<string, { name: string; logo: string }>;
    allegiances: { id: number; slug: string; name: string }[];
    /** Accounts (typically RSVPs) not yet playing, offered as a link for each member. */
    availableUsers: { id: number; name: string }[];
    /** Endpoint that finds BiggerHat accounts by name (`?q=`), for people who didn't RSVP. */
    searchUrl: string;
    /** Accounts already playing in this event; shown as unavailable in search results. */
    takenUserIds: number[];
}>();

const members = defineModel<TeamMemberDraft[]>({ required: true });

// ─── Find any account by name (members don't have to have RSVPed) ───
type Found = { id: number; name: string };
const queries = reactive<Record<number, string>>({});
const found = reactive<Record<number, Found[]>>({});
const searching = reactive<Record<number, boolean>>({});
const timers: Record<number, ReturnType<typeof setTimeout>> = {};
const onSearch = (i: number, q: string) => {
    queries[i] = q;
    clearTimeout(timers[i]);
    if (q.trim().length < 2) {
        found[i] = [];
        return;
    }
    timers[i] = setTimeout(async () => {
        searching[i] = true;
        try {
            const res = await fetch(`${props.searchUrl}?q=${encodeURIComponent(q.trim())}`, { headers: { Accept: 'application/json' } });
            if (res.ok && queries[i] === q) found[i] = ((await res.json()).users ?? []) as Found[];
        } finally {
            searching[i] = false;
        }
    }, 250);
};
const pickFound = (m: TeamMemberDraft, i: number, u: Found) => {
    m.user_id = u.id;
    m.user_name = u.name;
    if (!m.display_name.trim()) m.display_name = u.name;
    queries[i] = '';
    found[i] = [];
};

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
                    <div class="relative">
                        <Input
                            :model-value="queries[i] ?? ''"
                            placeholder="…or search all accounts by name"
                            class="mt-1 h-8 text-xs"
                            autocomplete="off"
                            :aria-label="`Search accounts for member ${i + 1}`"
                            @update:model-value="(q) => onSearch(i, String(q))"
                        />
                        <ul
                            v-if="found[i]?.length"
                            class="absolute z-20 mt-1 max-h-48 w-full overflow-y-auto rounded-md border bg-popover p-1 shadow-md"
                        >
                            <li v-for="u in found[i]" :key="u.id">
                                <button
                                    type="button"
                                    class="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-accent disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="takenUserIds.includes(u.id) && u.id !== m.user_id"
                                    @click="pickFound(m, i, u)"
                                >
                                    <span>{{ u.name }}</span>
                                    <span v-if="takenUserIds.includes(u.id) && u.id !== m.user_id" class="text-2xs text-muted-foreground"
                                        >already playing</span
                                    >
                                </button>
                            </li>
                        </ul>
                        <p v-else-if="searching[i]" class="mt-1 text-2xs text-muted-foreground">Searching…</p>
                    </div>
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
