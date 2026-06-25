<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import GameIcon from '@/components/GameIcon.vue';
import UpgradeFlipCard from '@/components/UpgradeFlipCard.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { categoryColor, categoryLabel, factionBackground } from '@/lib/gameDisplay';
import type { CrewMember, GameData, GamePlayer, SchemeData } from '@/types/game';
import { Check, Loader2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    game: GameData;
    schemes: SchemeData[];
    allMarkers: { id: number; name: string; slug: string }[];
    isSolo: boolean;
    mySlot: number;
    /** True once the local player has locked in their Turn 1 scheme. */
    schemeStepDone: boolean;
    submitting: boolean;
    myPlayer?: GamePlayer;
    opponentPlayer?: GamePlayer;
}>();

const emit = defineEmits<{
    'open-scheme': [scheme: SchemeData];
    'open-member-preview': [member: CrewMember];
    /** Scheme + notes payload for the shared setup-submit (parent owns the POST + reload). */
    confirm: [payload: Record<string, unknown>];
}>();

// Pending Turn 1 scheme selection — local form state, cleared on confirm.
const pendingSchemeId = ref<number | null>(null);
const pendingSchemeModel = ref('');
const pendingSchemeMarker = ref('');
const pendingSchemeTerrainNote = ref('');
const pendingScheme = computed(() => (pendingSchemeId.value ? props.schemes.find((s) => s.id === pendingSchemeId.value) : null));
const pendingSchemeReqs = computed(() => pendingScheme.value?.requirements ?? []);
const pendingModelReq = computed(() => pendingSchemeReqs.value.find((r: any) => r.type === 'select_model') ?? null);
const pendingHasMarkerReq = computed(() => pendingSchemeReqs.value.some((r: any) => r.type === 'select_marker'));
const pendingHasTerrainReq = computed(() => pendingSchemeReqs.value.some((r: any) => r.type === 'terrain_note'));
const pendingModelLabel = computed(() => {
    const req = pendingModelReq.value;
    if (!req) return '';
    const parts: string[] = [];
    if (req.unique) parts.push('Unique');
    parts.push(req.allegiance === 'friendly' ? 'Friendly' : 'Enemy');
    parts.push('Model');
    if (req.cost_operator && req.cost_value != null) parts.push(`(Cost ${req.cost_operator} ${req.cost_value})`);
    return parts.join(' ');
});
const pendingModelOptions = computed(() => {
    const req = pendingModelReq.value;
    if (!req) return [];
    const pool = req.allegiance === 'friendly' ? [...(props.myPlayer?.crew_members ?? [])] : [...(props.opponentPlayer?.crew_members ?? [])];
    return pool.filter((m: any) => {
        if (req.unique && (m.station === 'minion' || m.station === 'peon')) return false;
        if (req.cost_operator && req.cost_value != null && m.cost != null) {
            const cost = m.cost as number;
            const target = req.cost_value as number;
            if (req.cost_operator === '>' && !(cost > target)) return false;
            if (req.cost_operator === '<' && !(cost < target)) return false;
            if (req.cost_operator === '>=' && !(cost >= target)) return false;
            if (req.cost_operator === '<=' && !(cost <= target)) return false;
        }
        return true;
    });
});
const selectPendingScheme = (schemeId: number) => {
    pendingSchemeId.value = schemeId;
    pendingSchemeModel.value = '';
    pendingSchemeMarker.value = '';
    pendingSchemeTerrainNote.value = '';
};
const confirmPendingScheme = () => {
    if (!pendingSchemeId.value) return;
    // Scheme + its notes save atomically in one setup-endpoint call. The
    // standalone scheme-notes endpoint is in_progress-gated and can't be
    // used pregame, so folding the notes into the scheme submit avoids a
    // silent 422 that previously dropped the user's selections.
    const notes: Record<string, string | null> = {
        note: null,
        selected_model: pendingSchemeModel.value || null,
        selected_marker: pendingSchemeMarker.value || null,
        terrain_note: pendingSchemeTerrainNote.value || null,
    };
    const hasNotes = notes.selected_model || notes.selected_marker || notes.terrain_note;

    emit('confirm', {
        scheme_id: pendingSchemeId.value,
        ...(props.isSolo ? { slot: props.mySlot } : {}),
        ...(hasNotes ? { scheme_notes: notes } : {}),
    });
    pendingSchemeId.value = null;
};
</script>

<template>
    <Card class="mb-6">
        <CardContent class="p-4 sm:p-6">
            <h2 class="mb-1 font-semibold">Select Your Scheme</h2>
            <p v-if="schemeStepDone && !isSolo" class="mb-4 text-xs text-muted-foreground">
                <Loader2 class="mr-1 inline size-3 animate-spin" /> Waiting for opponent...
            </p>
            <p v-else-if="!schemeStepDone" class="mb-4 text-xs text-muted-foreground">
                Choose one scheme from the pool for Turn 1.
                <template v-if="isSolo"> You can set the opponent's scheme during gameplay.</template>
            </p>

            <template v-if="!schemeStepDone">
                <!-- Scheme list -->
                <div class="grid gap-2 sm:grid-cols-3">
                    <div
                        v-for="scheme in schemes"
                        :key="scheme.id"
                        class="flex items-center gap-2 rounded-lg border p-3 transition-colors"
                        :class="pendingSchemeId === scheme.id ? 'border-primary bg-primary/5' : ''"
                    >
                        <div class="min-w-0 flex-1">
                            <button class="text-sm font-medium text-primary hover:underline" @click="$emit('open-scheme', scheme)">
                                {{ scheme.name }}
                            </button>
                        </div>
                        <Button
                            size="sm"
                            class="shrink-0 text-xs"
                            :variant="pendingSchemeId === scheme.id ? 'default' : 'outline'"
                            :disabled="submitting"
                            @click="selectPendingScheme(scheme.id)"
                        >
                            {{ pendingSchemeId === scheme.id ? 'Selected' : 'Select' }}
                        </Button>
                    </div>
                </div>

                <!-- Requirement fields + confirm (shown when a scheme is selected) -->
                <div v-if="pendingScheme" class="mt-4 rounded-lg border border-primary/30 bg-primary/5 p-4">
                    <div class="mb-2 text-sm font-medium">{{ pendingScheme.name }} — Setup</div>

                    <!-- Prerequisite hint -->
                    <div v-if="pendingScheme.prerequisite" class="mb-3 text-[11px] italic text-muted-foreground">
                        {{ pendingScheme.prerequisite }}
                    </div>

                    <div v-if="pendingSchemeReqs.length" class="mb-3 space-y-2">
                        <!-- Model selection -->
                        <div v-if="pendingModelReq">
                            <label class="text-[10px] uppercase text-muted-foreground">{{ pendingModelLabel }}</label>
                            <select
                                v-if="pendingModelOptions.length"
                                v-model="pendingSchemeModel"
                                class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                            >
                                <option value="">Select...</option>
                                <option v-for="m in pendingModelOptions" :key="m.id" :value="m.display_name">
                                    {{ m.display_name }}<template v-if="m.cost != null"> ({{ m.cost }}ss)</template>
                                </option>
                            </select>
                            <input
                                v-else
                                v-model="pendingSchemeModel"
                                type="text"
                                placeholder="Type model name..."
                                class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                            />
                        </div>

                        <!-- Marker selection -->
                        <div v-if="pendingHasMarkerReq">
                            <label class="text-[10px] uppercase text-muted-foreground">Target Marker</label>
                            <select v-model="pendingSchemeMarker" class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs">
                                <option value="">Select...</option>
                                <option v-for="m in allMarkers" :key="m.id" :value="m.name">{{ m.name }}</option>
                            </select>
                        </div>

                        <!-- Terrain note -->
                        <div v-if="pendingHasTerrainReq">
                            <label class="text-[10px] uppercase text-muted-foreground">Terrain Note</label>
                            <input
                                v-model="pendingSchemeTerrainNote"
                                type="text"
                                placeholder="e.g. the building on the left..."
                                class="mt-0.5 w-full rounded border bg-background px-2 py-1 text-xs"
                            />
                        </div>
                    </div>

                    <div v-else class="mb-3 text-xs text-muted-foreground">No additional selections required for this scheme.</div>

                    <div class="flex items-center gap-2">
                        <Button size="sm" :disabled="submitting" @click="confirmPendingScheme">
                            <Loader2 v-if="submitting" class="mr-1.5 size-3 animate-spin" />
                            Confirm Scheme
                        </Button>
                        <Button size="sm" variant="ghost" @click="pendingSchemeId = null">Cancel</Button>
                    </div>
                </div>
            </template>
            <div v-else class="py-4 text-center text-sm text-muted-foreground"><Check class="inline size-5 text-green-500" /> Scheme selected</div>
        </CardContent>
    </Card>

    <!-- Crew lists visible during scheme select -->
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <div v-for="player in game.players" :key="'scheme-crew-' + player.id">
            <h3 class="mb-2 text-sm font-semibold">
                <FactionLogo v-if="player.faction" :faction="player.faction" class-name="mr-1 inline size-4" />
                {{ player.user?.name ?? player.opponent_name ?? 'Opponent' }}
                <span v-if="player.master_name" class="ml-1 text-xs font-normal text-muted-foreground">— {{ player.master_name }}</span>
            </h3>
            <div v-if="player.crew_members?.length" class="space-y-0.5">
                <div
                    v-for="member in player.crew_members"
                    :key="member.id"
                    :class="factionBackground(member.faction ?? player.faction ?? '')"
                    class="flex items-center justify-between rounded px-2 py-1 text-xs text-white"
                >
                    <div class="flex min-w-0 items-center gap-1.5">
                        <span
                            class="truncate font-medium"
                            :class="member.front_image ? 'cursor-pointer hover:underline' : ''"
                            @click="$emit('open-member-preview', member)"
                            >{{ member.display_name }}</span
                        >
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
            <div v-else-if="player.crew_skipped" class="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">
                Crew not tracked
            </div>
            <div v-else class="rounded-md border border-dashed p-3 text-center text-xs text-muted-foreground">No crew selected</div>

            <!-- Master's crew (upgrade) card — shown for both sides before turn 1. -->
            <div
                v-for="upgrade in (player.master?.crew_upgrades ?? []).filter(
                    (u: any) => u.id === (player.active_crew_upgrade_id ?? player.crew_build?.crew_upgrade_id),
                )"
                :key="'scheme-cu-' + upgrade.id"
                class="mt-2"
            >
                <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Crew Card — {{ upgrade.name }}</p>
                <div class="max-w-[260px] [&_img]:w-full">
                    <UpgradeFlipCard
                        :front-image="upgrade.front_image"
                        :back-image="upgrade.back_image"
                        :alt-text="upgrade.name"
                        :show-link="false"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
