<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import type { TournamentAward } from '@/types/tournament';
import { Award } from 'lucide-vue-next';

/** Side awards that have gone to someone, on the public event page. */
defineProps<{ awards: TournamentAward[] }>();
</script>

<template>
    <Card v-if="awards.length" class="mb-4" data-testid="awards">
        <CardContent class="p-4 sm:p-5">
            <h2 class="mb-2 flex items-center gap-1.5 text-sm font-semibold"><Award class="size-4 text-amber-500" aria-hidden="true" /> Awards</h2>
            <ul class="grid gap-x-6 gap-y-1.5 text-sm sm:grid-cols-2">
                <li v-for="a in awards" :key="a.id" class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-medium">{{ a.name }}</span>
                    <span>{{ a.player_name }}</span>
                    <span v-if="a.note" class="text-xs text-muted-foreground">— {{ a.note }}</span>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
