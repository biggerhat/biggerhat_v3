<script setup lang="ts">
import GameUpgradeDialog from '@/components/Game/GameUpgradeDialog.vue';
import { useGameApi } from '@/composables/useGameApi';
import { useToast } from '@/composables/useToast';
import { computed, ref } from 'vue';

/**
 * The upgrade and injury pickers for a model, the same two dialogs and the same saving as the play screen (Games/Show.vue), for pages that
 * list the crew without owning that logic (the tabletop board). The parent says which model to open them for; this saves the change through the
 * crew-member endpoint and tells the parent what the model now has.
 */
interface Catalog {
    id: number;
    name: string;
    front_image: string | null;
    back_image: string | null;
    plentiful?: number | null;
    power_bar_count?: number | null;
    description?: string | null;
    actions?: unknown[];
    abilities?: unknown[];
}
interface Member {
    id: number;
    game_player_id: number;
    display_name: string;
    attached_upgrades: any[] | null;
}

const props = defineProps<{
    gameUuid: string;
    /** Every model in the game, to count how many of an upgrade a crew already uses. */
    members: Member[];
    upgrades: Catalog[];
    /** Campaign games only: the injury catalogue. */
    injuries: Catalog[];
    /** Upgrades the crew builds list, offered first. */
    referenceIds: number[];
}>();
const emit = defineEmits<{ attached: [memberId: number, attached: any[]] }>();

const gameApi = useGameApi();
const toast = useToast();

const kind = ref<'upgrade' | 'injury'>('upgrade');
const open = ref(false);
const memberId = ref<number | null>(null);
const search = ref('');
/** The model's upgrades as last saved here, so the dialog shows a change at once. */
const attachedNow = ref<Record<number, any[]>>({});

const member = computed(() => {
    const m = props.members.find((x) => x.id === memberId.value);
    return m ? { ...m, attached_upgrades: attachedNow.value[m.id] ?? m.attached_upgrades ?? [] } : null;
});

function openFor(id: number, which: 'upgrade' | 'injury') {
    memberId.value = id;
    kind.value = which;
    search.value = '';
    open.value = true;
}
defineExpose({ openUpgrades: (id: number) => openFor(id, 'upgrade'), openInjury: (id: number) => openFor(id, 'injury') });

const catalog = computed(() => (kind.value === 'injury' ? props.injuries : props.upgrades));
const referenceIds = computed(() => new Set(kind.value === 'injury' ? [] : props.referenceIds));
const options = computed(() => {
    const q = search.value.toLowerCase();
    const list = q ? catalog.value.filter((u) => u.name.toLowerCase().includes(q)) : catalog.value;
    return [...list]
        .map((u) => ({ ...u, plentiful: u.plentiful ?? undefined }))
        .sort((a, b) => (referenceIds.value.has(a.id) ? 0 : 1) - (referenceIds.value.has(b.id) ? 0 : 1) || a.name.localeCompare(b.name));
});

/** How many models of this crew already carry the upgrade (not counting the one being edited): drives the per-crew limit. */
const usageCount = (upgradeId: number) => {
    if (kind.value === 'injury' || !member.value) return 0; // no limit on injuries
    return props.members.filter(
        (m) =>
            m.game_player_id === member.value!.game_player_id &&
            m.id !== member.value!.id &&
            (attachedNow.value[m.id] ?? m.attached_upgrades ?? []).some((u: any) => u.id === upgradeId),
    ).length;
};

async function toggle(picked: { id: number; name: string; front_image: string | null; back_image: string | null }) {
    const current = member.value;
    if (!current) return;
    const have = current.attached_upgrades;
    const attached = have.some((u: any) => u.id === picked.id);
    const entry = catalog.value.find((u) => u.id === picked.id);
    const row: Record<string, unknown> = {
        id: picked.id,
        name: picked.name,
        front_image: picked.front_image,
        back_image: picked.back_image,
        description: entry?.description ?? null,
        actions: entry?.actions ?? [],
        abilities: entry?.abilities ?? [],
    };
    // a power-bar upgrade starts empty: its bubbles fill as it is used (as on the play screen)
    if (kind.value === 'upgrade' && (entry?.power_bar_count ?? 0) > 0) row.current_power_bar = 0;
    const updated = attached ? have.filter((u: any) => u.id !== picked.id) : [...have, row];
    const before = attachedNow.value[current.id];
    attachedNow.value = { ...attachedNow.value, [current.id]: updated };
    const res = await gameApi.patch(route('games.play.crew.update', { game: props.gameUuid, gameCrewMember: current.id }), {
        attached_upgrades: updated,
    });
    if (!res.ok) {
        attachedNow.value = { ...attachedNow.value, [current.id]: before ?? have };
        toast.error('That was not saved.');
        return;
    }
    emit('attached', current.id, updated);
}
</script>

<template>
    <GameUpgradeDialog
        :open="open"
        :member="member"
        :options="options"
        :reference-ids="referenceIds"
        :search="search"
        :usage-count="usageCount"
        :title="kind === 'injury' ? 'Attach Injury' : undefined"
        :options-label="kind === 'injury' ? 'All Injuries' : undefined"
        @update:open="open = $event"
        @update:search="search = $event"
        @toggle="toggle"
    />
</template>
