<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { factionBackground } from '@/composables/useFactionColor';
import { categoryColor, categoryLabel, playerName } from '@/lib/gameDisplay';
import {
    GameStatus,
    type CrewMember,
    type DeploymentData,
    type GameData,
    type GamePlayer,
    type LootCardSummary,
    type SchemeData,
} from '@/types/game';
import { ArrowUpCircle, ChevronDown, Copy, Heart, QrCode, Star } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    game: GameData;
    isSolo: boolean;
    isBonanza: boolean;
    deployment: DeploymentData | null;
    schemes: SchemeData[];
    startingCrews?: Record<
        number,
        { display_name: string; faction: string; cost: number; hiring_category: string; front_image: string | null; back_image: string | null }[]
    >;
    myPlayer?: GamePlayer;
    opponentPlayer?: GamePlayer;
    /** Resolves a scheme by id (pool + reachable + current/next chains). */
    schemeLookup: Map<number, SchemeData>;
    /** Loot card catalog by id; empty on non-Bonanza / the summary payload. */
    lootCardCatalog: Map<number, LootCardSummary>;
}>();

defineEmits<{
    'open-scheme': [scheme: SchemeData];
    'open-deployment': [];
    'open-strategy': [];
    'open-qr': [url: string, title: string];
    'open-member-preview': [member: CrewMember];
    'open-upgrade-preview': [upgrade: { id: number; name: string; front_image: string | null; back_image: string | null }];
}>();

// Summary-only UI state (lives here, not in the parent monolith).
const summaryLinkCopied = ref(false);
const copySummaryLink = async () => {
    await navigator.clipboard.writeText(route('games.summary', props.game.uuid));
    summaryLinkCopied.value = true;
    setTimeout(() => (summaryLinkCopied.value = false), 2000);
};
const expandedTurn = ref<number | null>(null);

const findScheme = (id: number | null | undefined) => (id ? props.schemeLookup.get(id) : undefined);
const lootCardById = (id: number): LootCardSummary | null => props.lootCardCatalog.get(id) ?? null;

// Pure turn lookups over the (loosely-typed) stored turn rows.
const getPlayerTurn = (player: { turns?: any[] }, turnNumber: number) => (player.turns ?? []).find((t: any) => t.turn_number === turnNumber);
const getTurnSchemeInfo = (player: { turns?: any[] }, turnNumber: number): { schemeId: number | null; action: string | null } => {
    const turn = getPlayerTurn(player, turnNumber);
    if (!turn) return { schemeId: null, action: null };
    return { schemeId: turn.scheme_id ?? null, action: turn.scheme_action ?? null };
};
</script>

<template>
    <!-- Result banner -->
    <Card class="mb-4 overflow-hidden">
        <CardContent class="p-0">
            <div
                class="px-4 py-3 text-center"
                :class="game.status === GameStatus.Abandoned ? 'bg-muted' : game.is_tie ? 'bg-muted' : 'bg-amber-500/10'"
            >
                <div v-if="game.status === GameStatus.Abandoned" class="text-lg font-bold text-muted-foreground">Game Abandoned</div>
                <div v-else-if="game.is_tie" class="text-lg font-bold">Draw!</div>
                <div v-else-if="game.winner" class="text-lg font-bold text-amber-700 dark:text-amber-400">{{ game.winner.name }} Wins!</div>
                <div v-else-if="isSolo && game.winner_slot" class="text-lg font-bold text-amber-700 dark:text-amber-400">
                    {{ game.winner_slot === 1 ? playerName(myPlayer) : playerName(opponentPlayer) }} Wins!
                </div>
            </div>

            <!-- Final scores side by side. Bonanza is single-column
                 (only the user) and skips the Attacker/Defender role. -->
            <div :class="['border-t', isBonanza ? '' : 'grid grid-cols-2 divide-x']">
                <div
                    v-for="player in isBonanza ? game.players.filter((p) => p.slot === 1) : game.players"
                    :key="'final-' + player.id"
                    class="p-3 text-center"
                >
                    <div class="flex items-center justify-center gap-1.5">
                        <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-4" />
                        <span class="text-xs font-medium">{{ playerName(player) }}</span>
                        <Badge v-if="player.role && !isBonanza" variant="outline" class="px-1 py-0 text-[8px] capitalize">{{ player.role }}</Badge>
                    </div>
                    <div v-if="player.master_name" class="mt-0.5 text-[10px] text-muted-foreground">{{ player.master_name }}</div>
                    <div
                        class="mt-1 text-2xl font-bold"
                        :class="
                            (game.winner?.id === player.user?.id && game.winner) || (isSolo && game.winner_slot === player.slot)
                                ? 'text-amber-600 dark:text-amber-400'
                                : ''
                        "
                    >
                        {{ player.total_points }} <span class="text-sm font-normal text-muted-foreground">VP</span>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Share button -->
    <div v-if="game.status === GameStatus.Completed" class="mb-4 flex justify-center gap-2">
        <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="copySummaryLink">
            <Copy class="size-3" />
            {{ summaryLinkCopied ? 'Copied!' : 'Share Summary' }}
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5 text-xs" @click="$emit('open-qr', route('games.summary', game.uuid), 'Game Summary')">
            <QrCode class="size-3" />
            QR Code
        </Button>
    </div>

    <!-- Starting Crews -->
    <div v-if="startingCrews && Object.keys(startingCrews).length" class="mb-4 grid gap-4 sm:grid-cols-2">
        <div v-for="player in game.players" :key="'start-crew-' + player.id">
            <h3 class="mb-2 text-sm font-semibold">{{ playerName(player) }}'s Starting Crew</h3>
            <div v-if="!startingCrews[player.slot]?.length" class="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">
                No crew tracked
            </div>
            <div v-else class="space-y-0.5">
                <div
                    v-for="(member, mIdx) in startingCrews[player.slot]"
                    :key="'sc-' + player.slot + '-' + mIdx"
                    :class="factionBackground(member.faction ?? player.faction ?? '')"
                    class="flex items-center justify-between rounded px-2 py-1 text-xs text-white"
                >
                    <div class="flex min-w-0 items-center gap-1.5">
                        <span class="truncate font-medium">{{ member.display_name }}</span>
                        <Badge
                            v-if="member.hiring_category && member.hiring_category !== 'leader' && member.hiring_category !== 'totem'"
                            :class="categoryColor(member.hiring_category)"
                            class="shrink-0 px-1 py-0 text-[9px]"
                        >
                            {{ categoryLabel(member.hiring_category) }}
                        </Badge>
                    </div>
                    <div v-if="member.cost > 0" class="flex shrink-0 items-center font-bold">
                        {{ member.cost }}
                        <GameIcon type="soulstone" class-name="ml-0.5 h-3 inline-block" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scoring Breakdown -->
    <Card class="mb-4">
        <CardContent class="p-4">
            <h3 class="mb-3 text-sm font-semibold">Scoring Breakdown</h3>
            <div class="grid grid-cols-2 divide-x">
                <div v-for="player in game.players" :key="'score-bk-' + player.id" class="space-y-2 px-3 first:pl-0 last:pr-0">
                    <div class="flex items-center gap-1.5 text-xs font-medium">
                        <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-3.5" />
                        {{ playerName(player) }}
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded bg-muted/50 p-2 text-center">
                            <div class="text-lg font-bold">
                                {{ player.turns?.reduce((s: number, t: any) => s + (t.strategy_points ?? 0), 0) ?? 0 }}
                            </div>
                            <div class="text-[10px] text-muted-foreground">Strategy VP</div>
                        </div>
                        <div class="rounded bg-muted/50 p-2 text-center">
                            <div class="text-lg font-bold">{{ player.turns?.reduce((s: number, t: any) => s + (t.scheme_points ?? 0), 0) ?? 0 }}</div>
                            <div class="text-[10px] text-muted-foreground">Scheme VP</div>
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Scheme Timeline -->
    <Card class="mb-4">
        <CardContent class="p-4">
            <h3 class="mb-3 text-sm font-semibold">Scheme Timeline</h3>
            <div class="grid grid-cols-2 divide-x">
                <div v-for="player in game.players" :key="'scheme-tl-' + player.id" class="space-y-1.5 px-3 first:pl-0 last:pr-0">
                    <div class="flex items-center gap-1.5 text-xs font-medium">
                        <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-3.5" />
                        {{ playerName(player) }}
                    </div>
                    <div
                        v-for="turn in (player.turns ?? []).slice().sort((a: any, b: any) => a.turn_number - b.turn_number)"
                        :key="'stl-' + player.id + '-' + turn.turn_number"
                        class="flex items-center gap-1.5 text-[11px]"
                    >
                        <span class="w-5 shrink-0 text-muted-foreground">T{{ turn.turn_number }}</span>
                        <template v-if="turn.scheme_id && findScheme(turn.scheme_id)">
                            <button class="truncate font-medium hover:text-primary" @click="$emit('open-scheme', findScheme(turn.scheme_id)!)">
                                {{ findScheme(turn.scheme_id)?.name }}
                            </button>
                        </template>
                        <span v-else class="italic text-muted-foreground">Hidden</span>
                        <Badge
                            v-if="turn.scheme_action === 'scored'"
                            variant="outline"
                            class="shrink-0 border-green-500/50 px-1 py-0 text-[8px] text-green-600 dark:text-green-400"
                            >+{{ turn.scheme_points }}</Badge
                        >
                        <Badge
                            v-else-if="turn.scheme_action === 'discarded'"
                            variant="outline"
                            class="shrink-0 border-amber-500/50 px-1 py-0 text-[8px] text-amber-600 dark:text-amber-400"
                            >Discarded</Badge
                        >
                        <Badge v-else-if="turn.scheme_action === 'held'" variant="outline" class="shrink-0 px-1 py-0 text-[8px]">Held</Badge>
                    </div>
                    <div v-if="!(player.turns ?? []).length" class="text-xs text-muted-foreground">No turns recorded</div>
                </div>
            </div>
        </CardContent>
    </Card>

    <!-- Compact scenario rolldown -->
    <details class="mb-4 rounded-lg border">
        <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-muted-foreground hover:text-foreground">Encounter Details</summary>
        <div class="flex flex-wrap gap-x-4 gap-y-1 border-t px-3 py-2 text-xs">
            <div v-if="deployment">
                <span class="text-muted-foreground">Deployment:</span>
                <button class="ml-1 font-medium hover:text-primary" @click="$emit('open-deployment')">{{ deployment.label }}</button>
            </div>
            <div v-if="deployment?.image_url" class="my-2 flex justify-center">
                <img :src="deployment.image_url" :alt="deployment.label" class="max-h-48 rounded-lg" loading="lazy" />
            </div>
            <div v-if="game.strategy">
                <span class="text-muted-foreground">Strategy:</span>
                <button class="ml-1 font-medium hover:text-primary" @click="$emit('open-strategy')">{{ game.strategy.name }}</button>
            </div>
            <div v-if="schemes.length">
                <span class="text-muted-foreground">Scheme Pool:</span>
                <span v-for="(scheme, idx) in schemes" :key="scheme.id">
                    <span v-if="idx > 0">, </span>
                    <button class="font-medium hover:text-primary" @click="$emit('open-scheme', scheme)">{{ scheme.name }}</button>
                </span>
            </div>
            <div>
                <span class="text-muted-foreground">Size:</span>
                <span class="ml-1 font-medium">{{ game.encounter_size }}ss</span>
            </div>
            <div>
                <span class="text-muted-foreground">Season:</span>
                <span class="ml-1 font-medium">{{ game.season_label }}</span>
            </div>
        </div>
    </details>

    <!-- Turn-by-turn breakdown with scheme info -->
    <div v-if="game.players[0]?.turns?.length" class="mb-4 space-y-2">
        <h3 class="text-sm font-semibold">Turn-by-Turn Breakdown</h3>

        <div v-for="turn in Math.max(...game.players.map((p: any) => p.turns?.length ?? 0))" :key="'detail-' + turn">
            <Card class="overflow-hidden">
                <button
                    class="flex w-full items-center justify-between px-3 py-2 text-left transition-colors hover:bg-muted/50"
                    @click="expandedTurn === turn ? (expandedTurn = null) : (expandedTurn = turn)"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold">Turn {{ turn }}</span>
                        <div class="flex items-center gap-2 text-[11px]">
                            <template v-for="player in game.players" :key="'tscore-' + player.id + '-' + turn">
                                <span class="flex items-center gap-1">
                                    <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-3" />
                                    <span class="font-bold">
                                        +{{ (getPlayerTurn(player, turn)?.strategy_points ?? 0) + (getPlayerTurn(player, turn)?.scheme_points ?? 0) }}
                                    </span>
                                    <span class="text-muted-foreground">
                                        ({{
                                            player.turns
                                                ?.filter((t: any) => t.turn_number <= turn)
                                                .reduce((sum: number, t: any) => sum + (t.strategy_points ?? 0) + (t.scheme_points ?? 0), 0)
                                        }})
                                    </span>
                                </span>
                            </template>
                        </div>
                    </div>
                    <ChevronDown
                        class="size-3.5 shrink-0 text-muted-foreground transition-transform duration-200"
                        :class="expandedTurn === turn ? 'rotate-180' : ''"
                    />
                </button>

                <div v-if="expandedTurn === turn" class="border-t">
                    <div class="grid grid-cols-2 divide-x">
                        <div v-for="player in game.players" :key="'tdetail-' + player.id + '-' + turn" class="p-3">
                            <div class="mb-2 flex items-center gap-1.5">
                                <FactionLogo v-if="player.faction" :faction="player.faction" class-name="size-3.5" />
                                <span class="text-xs font-semibold">{{ playerName(player) }}</span>
                            </div>

                            <!-- Scores -->
                            <div class="space-y-0.5 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Strategy</span>
                                    <span class="font-medium">+{{ getPlayerTurn(player, turn)?.strategy_points ?? 0 }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-muted-foreground">Scheme</span>
                                    <span class="font-medium">+{{ getPlayerTurn(player, turn)?.scheme_points ?? 0 }}</span>
                                </div>
                            </div>

                            <!-- Scheme used this turn -->
                            <div
                                v-if="getTurnSchemeInfo(player, turn).schemeId || getTurnSchemeInfo(player, turn).action"
                                class="mt-2 rounded border border-dashed px-2 py-1 text-[10px]"
                            >
                                <template v-if="getTurnSchemeInfo(player, turn).schemeId">
                                    <span class="text-muted-foreground">Scheme:</span>
                                    <button
                                        class="ml-1 font-medium hover:text-primary"
                                        @click="$emit('open-scheme', findScheme(getTurnSchemeInfo(player, turn).schemeId)!)"
                                    >
                                        {{ findScheme(getTurnSchemeInfo(player, turn).schemeId)?.name }}
                                    </button>
                                </template>
                                <span v-else class="text-muted-foreground">Scheme: Hidden</span>
                                <Badge
                                    v-if="getTurnSchemeInfo(player, turn).action === 'held'"
                                    variant="outline"
                                    class="ml-1 border-blue-500/50 px-1 py-0 text-[8px] text-blue-600 dark:text-blue-400"
                                    >Held</Badge
                                >
                                <Badge
                                    v-if="getTurnSchemeInfo(player, turn).action === 'scored'"
                                    variant="outline"
                                    class="ml-1 border-green-500/50 px-1 py-0 text-[8px] text-green-600 dark:text-green-400"
                                    >Scored</Badge
                                >
                                <Badge
                                    v-if="getTurnSchemeInfo(player, turn).action === 'discarded'"
                                    variant="outline"
                                    class="ml-1 border-amber-500/50 px-1 py-0 text-[8px] text-amber-600 dark:text-amber-400"
                                    >Discarded</Badge
                                >

                                <!-- Scheme notes for this turn -->
                                <template v-if="getPlayerTurn(player, turn)?.scheme_notes">
                                    <div v-if="getPlayerTurn(player, turn).scheme_notes.selected_model" class="mt-1 text-muted-foreground">
                                        <span class="font-medium">Target:</span>
                                        {{ getPlayerTurn(player, turn).scheme_notes.selected_model }}
                                    </div>
                                    <div v-if="getPlayerTurn(player, turn).scheme_notes.selected_marker" class="mt-0.5 text-muted-foreground">
                                        <span class="font-medium">Marker:</span>
                                        {{ getPlayerTurn(player, turn).scheme_notes.selected_marker }}
                                    </div>
                                    <div v-if="getPlayerTurn(player, turn).scheme_notes.terrain_note" class="mt-0.5 text-muted-foreground">
                                        <span class="font-medium">Terrain:</span>
                                        {{ getPlayerTurn(player, turn).scheme_notes.terrain_note }}
                                    </div>
                                    <div v-if="getPlayerTurn(player, turn).scheme_notes.note" class="mt-0.5 italic text-muted-foreground">
                                        {{ getPlayerTurn(player, turn).scheme_notes.note }}
                                    </div>
                                </template>
                            </div>

                            <!-- Crew snapshot -->
                            <div v-if="getPlayerTurn(player, turn)?.crew_snapshot?.length" class="mt-2">
                                <div class="mb-1 text-[10px] font-medium uppercase text-muted-foreground">Crew</div>
                                <div class="space-y-0.5">
                                    <div
                                        :key="'snap-' + turn + '-' + player.id + '-' + mIdx"
                                        v-for="(member, mIdx) in getPlayerTurn(player, turn).crew_snapshot"
                                    >
                                        <div
                                            :class="factionBackground(member.faction ?? player.faction ?? '')"
                                            class="flex items-center justify-between rounded px-1.5 py-0.5 text-[11px] text-white"
                                            :style="member.is_killed ? 'opacity: 0.4; text-decoration: line-through' : ''"
                                        >
                                            <div class="flex min-w-0 items-center gap-1">
                                                <span class="truncate font-medium">{{ member.display_name }}</span>
                                                <Badge v-if="member.is_summoned" class="bg-cyan-400/20 px-0.5 py-0 text-[8px] text-cyan-200">S</Badge>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <template v-if="member.attached_tokens?.length">
                                                    <div
                                                        v-for="token in member.attached_tokens"
                                                        :key="token.id"
                                                        class="rounded bg-cyan-900/50 px-0.5 text-[8px] text-cyan-200"
                                                    >
                                                        {{ token.name }}
                                                    </div>
                                                </template>
                                                <span class="flex items-center gap-0.5 font-bold">
                                                    <Heart
                                                        class="size-2.5"
                                                        :class="
                                                            member.is_killed
                                                                ? 'text-red-400'
                                                                : member.current_health <= Math.ceil(member.max_health / 2)
                                                                  ? 'text-red-300'
                                                                  : ''
                                                        "
                                                    />
                                                    {{ member.current_health }}/{{ member.max_health }}
                                                </span>
                                            </div>
                                        </div>
                                        <!-- Attached upgrades in snapshot -->
                                        <div v-if="member.attached_upgrades?.length" class="space-y-0.5 pl-3">
                                            <div
                                                v-for="upgrade in member.attached_upgrades"
                                                :key="'su-' + upgrade.id"
                                                class="flex items-center gap-1 rounded bg-black/10 px-1 py-0 text-[9px] text-amber-300"
                                            >
                                                <ArrowUpCircle class="size-2 shrink-0" />
                                                <template v-if="(upgrade as any).loot_side">
                                                    <span class="rounded bg-amber-400 px-0.5 text-[8px] font-bold text-black">
                                                        {{ (upgrade as any).loot_side.toUpperCase() }}
                                                    </span>
                                                    <span
                                                        v-if="lootCardById((upgrade as any).loot_card_id)"
                                                        class="rounded border border-white/30 bg-black/30 px-0.5 font-mono text-[8px] tabular-nums"
                                                    >
                                                        {{ lootCardById((upgrade as any).loot_card_id)?.value_label }}
                                                    </span>
                                                </template>
                                                <span class="truncate">{{ upgrade.name }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else-if="player.faction" class="mt-2 text-[10px] text-muted-foreground">No crew tracked</div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </div>

    <!-- Final crew states with crew upgrades -->
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div v-for="player in game.players" :key="'crew-' + player.id">
            <h3 class="mb-2 text-sm font-semibold">{{ playerName(player) }}'s Final Crew</h3>
            <div v-if="!player.crew_members?.length && player.faction" class="flex items-center gap-2 rounded-md border border-dashed p-3">
                <FactionLogo :faction="player.faction" class-name="size-6" />
                <div class="text-xs">
                    <div v-if="player.master_name" class="font-medium">{{ player.master_name }}</div>
                    <span class="text-muted-foreground">No crew tracked</span>
                </div>
            </div>
            <template v-else>
                <!-- Crew upgrades -->
                <div v-if="player.master?.crew_upgrades?.length" class="mb-1.5 space-y-0.5">
                    <div
                        v-for="upgrade in player.master.crew_upgrades"
                        :key="'fu-' + upgrade.id"
                        class="flex items-center gap-1.5 rounded-md border px-2 py-1.5 text-sm"
                        :class="[
                            player.crew_build?.crew_upgrade_id === upgrade.id
                                ? 'border-amber-500/50 bg-amber-500/10'
                                : 'border-border/50 bg-accent/30 opacity-60',
                            upgrade.front_image ? 'cursor-pointer hover:bg-accent' : '',
                        ]"
                        @click="$emit('open-upgrade-preview', upgrade)"
                    >
                        <Star
                            class="size-3.5 shrink-0"
                            :class="player.crew_build?.crew_upgrade_id === upgrade.id ? 'fill-amber-500 text-amber-500' : 'text-muted-foreground'"
                        />
                        <span class="font-semibold">{{ upgrade.name }}</span>
                    </div>
                </div>
                <!-- Crew members -->
                <div class="space-y-1">
                    <div
                        v-for="member in player.crew_members"
                        :key="member.id"
                        :class="factionBackground(member.faction ?? player.faction ?? '')"
                        class="rounded-md border border-white/20 px-2 py-1.5 text-white"
                        :style="member.is_killed ? 'opacity: 0.4; text-decoration: line-through' : ''"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex min-w-0 items-center gap-1">
                                <span
                                    class="truncate text-sm font-semibold"
                                    :class="member.front_image ? 'cursor-pointer hover:underline' : ''"
                                    @click="$emit('open-member-preview', member)"
                                >
                                    {{ member.display_name }}
                                </span>
                                <Badge v-if="member.is_summoned" variant="secondary" class="bg-cyan-500/20 px-1 py-0 text-[9px] text-cyan-200"
                                    >Summoned</Badge
                                >
                            </div>
                            <span class="flex min-w-[3rem] items-center justify-center gap-0.5 text-xs font-bold">
                                <Heart
                                    class="size-3"
                                    :class="
                                        member.is_killed
                                            ? 'text-red-400'
                                            : member.current_health <= Math.ceil(member.max_health / 2)
                                              ? 'text-red-300'
                                              : ''
                                    "
                                />
                                {{ member.current_health }}/{{ member.max_health }}
                            </span>
                        </div>
                        <div v-if="member.attached_tokens?.length" class="mt-0.5 flex flex-wrap gap-1">
                            <Badge
                                v-for="token in member.attached_tokens"
                                :key="token.id"
                                variant="secondary"
                                class="border border-cyan-500/50 bg-cyan-900/60 px-1 py-0 text-[9px] text-cyan-200"
                                >{{ token.name }}</Badge
                            >
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
