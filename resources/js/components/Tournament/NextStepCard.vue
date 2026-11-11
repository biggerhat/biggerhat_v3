<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { STEP_STAGES, type NextStep, type StepStage } from '@/composables/useTournamentNextStep';
import { ArrowRight, Check, Loader2 } from 'lucide-vue-next';

/** The organizer's "what do I do now" card: a four-stage progress strip plus the single next action. */
const props = defineProps<{ step: NextStep }>();

const stageState = (key: StepStage): 'done' | 'current' | 'upcoming' => {
    const index = (k: StepStage) => STEP_STAGES.findIndex((x) => x.key === k);
    if (key === props.step.stage) return 'current';
    return index(key) < index(props.step.stage) ? 'done' : 'upcoming';
};
</script>

<template>
    <Card class="mb-4 border-primary/30 bg-primary/5">
        <CardContent class="space-y-3 p-4">
            <ol class="flex items-center gap-1 text-xs" aria-label="Tournament progress">
                <li
                    v-for="(stage, i) in STEP_STAGES"
                    :key="stage.key"
                    class="flex items-center gap-1"
                    :aria-current="stageState(stage.key) === 'current' ? 'step' : undefined"
                >
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 font-medium"
                        :class="
                            stageState(stage.key) === 'current'
                                ? 'bg-primary text-primary-foreground'
                                : stageState(stage.key) === 'done'
                                  ? 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-400'
                                  : 'bg-muted text-muted-foreground'
                        "
                    >
                        <Check v-if="stageState(stage.key) === 'done'" class="size-3" />
                        {{ stage.label }}
                    </span>
                    <span v-if="i < STEP_STAGES.length - 1" class="h-px w-3 bg-border sm:w-6" aria-hidden="true" />
                </li>
            </ol>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm font-semibold">{{ step.title }}</div>
                    <div class="text-xs text-muted-foreground sm:text-sm">{{ step.detail }}</div>
                </div>
                <Button v-if="step.action" class="shrink-0 gap-1.5" :disabled="step.disabled" @click="step.action">
                    <Loader2 v-if="step.disabled" class="size-4 animate-spin" />
                    {{ step.actionLabel }}
                    <ArrowRight v-if="!step.disabled" class="size-4" />
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
