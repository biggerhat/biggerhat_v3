<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { TIEBREAKER_LABELS, type TournamentRuleset } from '@/types/tournament';
import { computed } from 'vue';

/** Plain-language "how this event is scored", built from the event's effective scoring and enabled variants. */
const props = defineProps<{ ruleset: TournamentRuleset }>();

const lines = computed(() => {
    const s = props.ruleset.scoring;
    const out: string[] = [];
    const outcome = (label: string, tp: number) => `${label} ${s.base_tp + tp} TP`;
    if (s.diff_adds_to_tp) {
        out.push(
            `Each game is worth ${s.base_tp} TP, plus your differential${s.diff_cap ? ` (capped at ±${s.diff_cap})` : ''}, plus ${s.win_tp} for a win.`,
        );
    } else {
        out.push(`${outcome('Win', s.win_tp)}, ${outcome('tie', s.tie_tp)}, ${outcome('loss', s.loss_tp)}.`);
        if (s.diff_cap) out.push(`Differential is capped at ±${s.diff_cap} per game.`);
    }
    out.push(`Bye: ${s.bye_tp} TP / ${s.bye_diff >= 0 ? '+' : ''}${s.bye_diff} DIFF / ${s.bye_vp} VP.`);
    out.push(`Forfeit: the winner scores ${s.forfeit_vp} VP, the forfeiting player ${s.forfeit_loser_tp} TP and −${s.forfeit_vp} DIFF.`);
    out.push(`Skipping a round without dropping costs −${s.bye_diff} DIFF for each missed round.`);
    out.push(`Standings: TP${props.ruleset.tiebreakers.map((k) => ' → ' + TIEBREAKER_LABELS[k]).join('')}. Equal players share a placing.`);
    return out;
});

const variants = computed(() =>
    props.ruleset.variants
        .filter((v) => props.ruleset.active_variants[v.key] !== undefined)
        .map((v) => ({ ...v, value: props.ruleset.active_variants[v.key] })),
);
</script>

<template>
    <div class="space-y-3 text-sm">
        <div>
            <div class="mb-1 flex items-center gap-2">
                <h3 class="text-sm font-semibold">How scoring works</h3>
                <Badge v-if="ruleset.scoring_customized" variant="outline" class="text-2xs">Modified from {{ ruleset.label }}</Badge>
            </div>
            <ul class="list-disc space-y-0.5 pl-5 text-xs text-muted-foreground">
                <li v-for="l in lines" :key="l">{{ l }}</li>
            </ul>
        </div>
        <div v-if="variants.length">
            <h3 class="mb-1 text-sm font-semibold">Rule variants in play</h3>
            <ul class="space-y-1.5">
                <li v-for="v in variants" :key="v.key" class="text-xs">
                    <span class="font-medium"
                        >{{ v.label }}<template v-if="v.param"> ({{ v.value }})</template></span
                    >
                    <span class="text-muted-foreground"> — {{ v.description }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
