<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { isScoredResult } from '@/types/tournament';
import { computed } from 'vue';

/**
 * One pairing on the public tournament page: stacked on phones, a single dense row from `sm` up.
 * Both layouts read from the same `sides` data, so a side's logo / name / leader / score logic lives once.
 */
interface RowPlayer {
    id: number;
    display_name: string;
    faction: string | null;
    allegiance?: { slug: string } | null;
    garrison?: { share_code?: string | null } | null;
    /** Team events: the people behind this competitor. */
    members?: { display_name: string }[];
}

interface RowGame {
    id: number;
    table_number: number | null;
    player_one_id: number;
    player_two_id: number | null;
    player_one_faction: string | null;
    player_two_faction: string | null;
    player_one_master: string | null;
    player_one_title: string | null;
    player_two_master: string | null;
    player_two_title: string | null;
    player_one_vp: number | null;
    player_two_vp: number | null;
    is_bye: boolean;
    is_forfeit: boolean;
    result: string;
    tracker_game: { uuid: string; status?: string; current_turn?: number; max_turns?: number | null } | null;
}

const props = defineProps<{
    game: RowGame;
    players: RowPlayer[];
    myPlayerId: number | null;
    /** The round's status, so a pairing can say whether it is waiting, in play or final. */
    roundStatus?: string;
    /** The organizer's name for this game's table, if any. */
    tableName?: string | null;
}>();

const byId = computed(() => new Map(props.players.map((p) => [p.id, p])));

// A result is official once it is scored (self-reports that are still pending don't count).
const played = computed(() => isScoredResult(props.game.result));

const sides = computed(() =>
    (['one', 'two'] as const).map((key) => {
        const id = props.game[`player_${key}_id`];
        const p = id === null ? undefined : byId.value.get(id);
        const other = key === 'one' ? props.game.player_two_vp : props.game.player_one_vp;
        const vp = props.game[`player_${key}_vp`];
        return {
            key,
            id,
            name: p?.display_name ?? 'TBD',
            members: p?.members?.map((m) => m.display_name).join(' · ') || null,
            faction: props.game[`player_${key}_faction`] || p?.faction || null,
            allegiance: p?.allegiance?.slug ?? null,
            garrison: p?.garrison?.share_code ? route('tos.garrisons.shared', p.garrison.share_code) : null,
            leader: props.game[`player_${key}_title`] || props.game[`player_${key}_master`] || null,
            vp,
            winning: played.value && (vp ?? 0) > (other ?? 0),
            losing: played.value && (vp ?? 0) < (other ?? 0),
            isMe: props.myPlayerId !== null && id === props.myPlayerId,
        };
    }),
);

const tracker = computed(() => {
    const t = props.game.tracker_game;
    if (!t?.status || t.status === 'completed') return null;
    const tone =
        t.status === 'in_progress'
            ? 'border-amber-400 text-amber-700 dark:border-amber-500/70 dark:text-amber-300'
            : t.status === 'abandoned'
              ? 'border-destructive/60 text-destructive'
              : 'text-muted-foreground';
    const label =
        t.status === 'in_progress' ? `T${t.current_turn}/${t.max_turns ?? 5}` : t.status === 'abandoned' ? 'Abandoned' : t.status.replace('_', ' ');
    return { tone, label };
});

// One chip says where this match is: waiting for the round to start, being played, or final.
const status = computed<{ label: string; tone: string } | null>(() => {
    if (props.game.is_bye) return null;
    if (played.value) return { label: 'Final', tone: 'border-emerald-500/50 text-emerald-700 dark:text-emerald-400' };
    if (props.roundStatus === 'in_progress') {
        return tracker.value
            ? { label: `Playing · ${tracker.value.label}`, tone: tracker.value.tone }
            : { label: 'Playing', tone: 'border-amber-400/70 text-amber-700 dark:text-amber-300' };
    }
    if (props.roundStatus === 'setup') return { label: 'Paired', tone: 'text-muted-foreground' };
    return null;
});
const hasScores = computed(() => props.game.player_one_vp !== null || props.game.player_two_vp !== null);
const nameTone = (s: { isMe: boolean; winning: boolean; losing: boolean }) => [
    s.isMe ? 'text-primary' : '',
    s.winning ? 'font-semibold' : '',
    s.losing ? 'text-muted-foreground' : '',
];
const observeUrl = computed(() => (props.game.tracker_game?.uuid ? route('games.observe', props.game.tracker_game.uuid) : null));
const scoreTone = (winning: boolean) => (winning ? 'font-bold text-green-600 dark:text-green-400' : '');
</script>

<template>
    <div class="rounded-md border text-sm" :class="sides.some((s) => s.isMe) ? 'border-primary/40 bg-primary/5' : ''">
        <!-- Phones: a stacked card, one line per side -->
        <div class="flex flex-col gap-1 p-2.5 sm:hidden">
            <div class="flex items-center gap-2 text-2xs text-muted-foreground">
                <span v-if="game.table_number"
                    >Table {{ game.table_number }}<template v-if="tableName"> · {{ tableName }}</template></span
                >
                <Badge v-if="game.is_bye" variant="outline" class="ml-auto text-2xs">BYE</Badge>
                <Badge v-if="game.is_forfeit" variant="destructive" class="ml-auto px-1 py-0 text-2xs">Forfeit</Badge>
                <Badge v-else-if="game.result === 'agreed'" variant="outline" class="ml-auto px-1 py-0 text-2xs">Agreed</Badge>
                <Badge v-if="status" variant="outline" class="ml-auto px-1 py-0 text-2xs" :class="status.tone">{{ status.label }}</Badge>
            </div>

            <div v-for="s in game.is_bye ? sides.slice(0, 1) : sides" :key="s.key" class="flex items-center gap-2">
                <FactionLogo v-if="s.faction" :faction="s.faction" class-name="size-5 shrink-0" />
                <AllegianceLogo v-else-if="s.allegiance" :allegiance="s.allegiance" class-name="size-5 shrink-0" />
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium" :class="nameTone(s)">{{ s.name }}</div>
                    <div v-if="s.members" class="truncate text-2xs text-muted-foreground">{{ s.members }}</div>
                    <div v-if="s.leader" class="truncate text-2xs text-muted-foreground">{{ s.leader }}</div>
                    <a v-if="s.garrison" :href="s.garrison" target="_blank" rel="noopener" class="text-2xs text-primary hover:underline">Garrison</a>
                </div>
                <span v-if="!game.is_bye && hasScores" class="shrink-0 font-mono text-base tabular-nums" :class="scoreTone(s.winning)">{{
                    s.vp ?? '–'
                }}</span>
            </div>

            <a
                v-if="observeUrl"
                :href="observeUrl"
                target="_blank"
                rel="noopener"
                class="mt-1 self-end text-xs font-medium text-primary hover:underline"
            >
                View game →
            </a>
        </div>

        <!-- sm and up: one row — side one, score, side two -->
        <div class="hidden items-center gap-2 px-2 py-1.5 sm:flex">
            <span v-if="game.table_number" class="shrink-0 text-2xs text-muted-foreground" :title="tableName ?? undefined"
                >T{{ game.table_number }}</span
            >

            <div class="flex min-w-0 flex-1 items-center gap-1.5">
                <FactionLogo v-if="sides[0].faction" :faction="sides[0].faction" class-name="size-4 shrink-0" />
                <AllegianceLogo v-else-if="sides[0].allegiance" :allegiance="sides[0].allegiance" class-name="size-4 shrink-0" />
                <span class="truncate font-medium" :class="nameTone(sides[0])" :title="sides[0].members ?? undefined">{{ sides[0].name }}</span>
                <a
                    v-if="sides[0].garrison"
                    :href="sides[0].garrison"
                    target="_blank"
                    rel="noopener"
                    class="shrink-0 text-2xs text-primary hover:underline"
                    >Garrison</a
                >
                <span v-if="sides[0].leader" class="truncate text-2xs text-muted-foreground">· {{ sides[0].leader }}</span>
            </div>

            <Badge v-if="game.is_bye" variant="outline" class="shrink-0 text-2xs">BYE</Badge>
            <template v-else>
                <div v-if="hasScores" class="flex shrink-0 items-center gap-1 font-mono text-xs tabular-nums">
                    <span :class="scoreTone(sides[0].winning)">{{ sides[0].vp ?? '–' }}</span>
                    <span class="text-muted-foreground">-</span>
                    <span :class="scoreTone(sides[1].winning)">{{ sides[1].vp ?? '–' }}</span>
                </div>
                <span v-else class="shrink-0 text-2xs uppercase tracking-wide text-muted-foreground">vs</span>

                <div class="flex min-w-0 flex-1 items-center justify-end gap-1.5 text-right">
                    <span v-if="sides[1].leader" class="truncate text-2xs text-muted-foreground">{{ sides[1].leader }} ·</span>
                    <span class="truncate font-medium" :class="nameTone(sides[1])" :title="sides[1].members ?? undefined">{{ sides[1].name }}</span>
                    <a
                        v-if="sides[1].garrison"
                        :href="sides[1].garrison"
                        target="_blank"
                        rel="noopener"
                        class="shrink-0 text-2xs text-primary hover:underline"
                        >Garrison</a
                    >
                    <FactionLogo v-if="sides[1].faction" :faction="sides[1].faction" class-name="size-4 shrink-0" />
                    <AllegianceLogo v-else-if="sides[1].allegiance" :allegiance="sides[1].allegiance" class-name="size-4 shrink-0" />
                </div>

                <Badge v-if="game.is_forfeit" variant="destructive" class="shrink-0 px-1 py-0 text-2xs">Forfeit</Badge>
                <Badge v-else-if="game.result === 'agreed'" variant="outline" class="shrink-0 px-1 py-0 text-2xs">Agreed</Badge>
                <Badge v-if="status" variant="outline" class="shrink-0 px-1 py-0 text-2xs" :class="status.tone">{{ status.label }}</Badge>
                <a
                    v-if="observeUrl"
                    :href="observeUrl"
                    target="_blank"
                    rel="noopener"
                    class="shrink-0 text-2xs font-medium text-primary hover:underline"
                    >View</a
                >
            </template>
        </div>
    </div>
</template>
