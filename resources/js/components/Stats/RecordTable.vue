<script setup lang="ts">
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

/**
 * Wins, losses and ties for each value of something (a faction, a master, a strategy): one row each. A win rate based on fewer games
 * than the server calls reliable is shown as a dash instead, so a 2 game "100%" is never read as a result.
 */
export interface RecordRow {
    key: string;
    games: number;
    wins: number;
    losses: number;
    ties: number;
    win_rate: number | null;
    avg_vp: number | null;
    avg_opp_vp: number | null;
    reliable: boolean;
    interval?: { low: number; high: number } | null;
}

defineProps<{
    rows: RecordRow[];
    /** The column heading for the value (Faction, Master, ...). */
    label: string;
    /** Turns a value into the words shown (faction codes into names). */
    format?: (key: string) => string;
    /** Rows shown; the rest are left out (the table says so). */
    limit?: number;
    testid?: string;
}>();
</script>

<template>
    <div :data-testid="testid">
        <div class="overflow-x-auto">
            <Table class="text-xs">
                <TableHeader>
                    <TableRow>
                        <TableHead scope="col">{{ label }}</TableHead>
                        <TableHead scope="col" class="text-right">Games</TableHead>
                        <TableHead scope="col" class="text-right">W-L-T</TableHead>
                        <TableHead scope="col" class="text-right">Win %</TableHead>
                        <TableHead scope="col" class="text-right">Avg VP</TableHead>
                        <TableHead scope="col" class="hidden text-right sm:table-cell">Opp. VP</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="r in limit ? rows.slice(0, limit) : rows" :key="r.key">
                        <TableCell class="max-w-[12rem] truncate font-medium" :title="format ? format(r.key) : r.key">{{
                            format ? format(r.key) : r.key
                        }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ r.games }}</TableCell>
                        <TableCell class="text-right tabular-nums">{{ r.wins }}-{{ r.losses }}-{{ r.ties }}</TableCell>
                        <TableCell class="text-right tabular-nums">
                            <span v-if="r.reliable" :title="r.interval ? `Likely between ${r.interval.low}% and ${r.interval.high}%` : undefined"
                                >{{ r.win_rate }}%</span
                            >
                            <span v-else class="text-muted-foreground" title="Too few games for a percentage">–</span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">{{ r.avg_vp ?? '–' }}</TableCell>
                        <TableCell class="hidden text-right tabular-nums sm:table-cell">{{ r.avg_opp_vp ?? '–' }}</TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <p v-if="limit && rows.length > limit" class="mt-1 text-2xs text-muted-foreground">Showing the {{ limit }} with the most games.</p>
    </div>
</template>
