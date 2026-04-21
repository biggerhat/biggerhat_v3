<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { factionBackground } from '@/composables/useFactionColor';
import type { StandingEntry } from '@/types/tournament';
import { Crown } from 'lucide-vue-next';

/**
 * Tournament standings table — used identically by the public View page and
 * the organizer Manage page. Keeps the SoS column, joint-rank rendering,
 * faction colors, and ringer/dropped badges in one place.
 */
const props = defineProps<{
    standings: StandingEntry[];
    /** Highlight a specific player_id (used by View to show "you" in the standings). */
    highlightPlayerId?: number | null;
}>();
</script>

<template>
    <Card>
        <CardContent class="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/40 text-left text-[10px] uppercase tracking-wider text-muted-foreground">
                            <th class="px-2 py-2.5 font-semibold sm:px-3">#</th>
                            <th class="px-2 py-2.5 font-semibold sm:px-3">Player</th>
                            <th class="px-1 py-2.5 text-center font-semibold sm:px-3">TP</th>
                            <th class="px-1 py-2.5 text-center font-semibold sm:px-3" title="Strength of Schedule (sum of opponents' TP)">SoS</th>
                            <th class="px-1 py-2.5 text-center font-semibold sm:px-3">Diff</th>
                            <th class="px-1 py-2.5 text-center font-semibold sm:px-3">VP</th>
                            <th class="hidden px-3 py-2.5 text-center font-semibold sm:table-cell">Played</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!props.standings.length">
                            <td colspan="7" class="px-3 py-8 text-center text-muted-foreground">No results yet</td>
                        </tr>
                        <tr
                            v-for="entry in props.standings"
                            :key="entry.player_id"
                            :class="[
                                factionBackground(entry.faction),
                                entry.faction ? 'text-white' : 'border-b last:border-0',
                                entry.is_ringer ? 'opacity-50' : '',
                                props.highlightPlayerId && entry.player_id === props.highlightPlayerId ? 'ring-2 ring-inset ring-primary/60' : '',
                            ]"
                        >
                            <td class="px-2 py-2 font-bold tabular-nums sm:px-3">{{ entry.rank ?? '-' }}</td>
                            <td class="px-2 py-2 sm:px-3">
                                <div class="flex items-center gap-1.5">
                                    <FactionLogo
                                        v-if="entry.faction"
                                        :faction="entry.faction"
                                        class-name="size-4 shrink-0 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]"
                                    />
                                    <Crown
                                        v-if="entry.rank === 1 && !entry.is_ringer"
                                        class="size-3.5 shrink-0 text-amber-300 drop-shadow-[0_1px_2px_rgba(0,0,0,0.5)]"
                                    />
                                    <span class="truncate text-xs font-medium sm:text-sm">{{ entry.display_name }}</span>
                                    <Badge
                                        v-if="entry.is_ringer"
                                        variant="outline"
                                        class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/80 sm:inline-flex"
                                        >Ringer</Badge
                                    >
                                    <Badge
                                        v-if="entry.is_dropped"
                                        variant="outline"
                                        class="hidden shrink-0 border-white/40 px-1 py-0 text-[9px] text-white/60 sm:inline-flex"
                                        >Dropped</Badge
                                    >
                                </div>
                            </td>
                            <td class="px-1 py-2 text-center font-bold tabular-nums sm:px-3">{{ entry.total_tp }}</td>
                            <td class="px-1 py-2 text-center font-medium tabular-nums sm:px-3">{{ entry.total_sos }}</td>
                            <td class="px-1 py-2 text-center font-medium tabular-nums sm:px-3">{{ entry.total_diff > 0 ? '+' : '' }}{{ entry.total_diff }}</td>
                            <td class="px-1 py-2 text-center tabular-nums sm:px-3">{{ entry.total_vp }}</td>
                            <td class="hidden px-3 py-2 text-center tabular-nums opacity-70 sm:table-cell">{{ entry.rounds_played }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
