<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { flipLabel, ROLE_LABELS, type CoopClassData } from './types';

defineProps<{ item: CoopClassData }>();
</script>

<template>
    <Card :id="`class-${item.slug}`" class="h-full">
        <CardContent class="space-y-3 p-4 text-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold">{{ item.name }}</h3>
                    <div class="text-xs text-muted-foreground">{{ ROLE_LABELS[item.role] }}<span v-if="item.is_unique"> · Unique</span></div>
                </div>
                <div class="flex flex-wrap gap-1.5 text-xs">
                    <Badge variant="outline">Res TN {{ item.resistance }}</Badge>
                    <Badge v-if="item.speed !== null" variant="outline">Sp {{ item.speed }}</Badge>
                    <Badge variant="secondary">
                        {{ item.max_health }} health{{ item.elite_max_health ? ` (${item.elite_max_health} elite)` : '' }}
                    </Badge>
                </div>
            </div>

            <div v-for="ability in item.abilities ?? []" :key="ability.name">
                <span class="font-medium">{{ ability.name }}:</span> {{ ability.text }}
            </div>

            <div v-if="item.actions?.length" class="space-y-2">
                <div v-for="action in item.actions" :key="action.name" class="rounded-md border p-2">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <span class="font-medium">{{ action.name }}</span>
                        <span class="text-xs tabular-nums text-muted-foreground">
                            {{ action.kind === 'attack' ? 'Attack' : 'Tactical' }} · Rg {{ action.rg || '—' }} · Skl {{ action.skl || '—' }} · Rst
                            {{ action.rst || '—' }} · TN {{ action.tn || '—' }} · Dmg {{ action.dmg || '—' }}
                        </span>
                    </div>
                    <p v-if="action.text" class="mt-1 text-muted-foreground">{{ action.text }}</p>
                    <p v-for="trigger in action.triggers ?? []" :key="trigger.name" class="mt-1">
                        <span class="font-medium">{{ trigger.name }}:</span> {{ trigger.text }}
                    </p>
                </div>
            </div>

            <div v-if="item.behavior?.length">
                <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Behavior (flip an NP card for the role)</div>
                <ol class="space-y-1.5">
                    <li v-for="(row, i) in item.behavior" :key="i" class="flex gap-3 rounded-md bg-muted/40 p-2">
                        <div class="w-16 shrink-0 text-xs">
                            <div class="font-semibold tabular-nums">Flip {{ flipLabel(row) }}</div>
                            <div v-if="row.ideal_range" class="text-muted-foreground">Ideal {{ row.ideal_range }}</div>
                        </div>
                        <ol class="list-inside list-decimal">
                            <li v-for="(effect, j) in row.effects" :key="j">{{ effect }}</li>
                        </ol>
                    </li>
                </ol>
            </div>

            <div v-if="item.target_preference?.length" class="text-xs">
                <span class="font-semibold">Target preference:</span> {{ item.target_preference.join(' → ') }}
            </div>
            <div v-if="item.elite_modifications" class="text-xs">
                <span class="font-semibold text-destructive">Elite:</span> {{ item.elite_modifications }}
            </div>
        </CardContent>
    </Card>
</template>
