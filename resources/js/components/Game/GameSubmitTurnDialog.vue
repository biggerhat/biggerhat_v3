<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Loader2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    open: boolean;
    turnNumber: number;
    strategyPoints: number;
    strategyBonusUsed: boolean;
    schemePoints: number;
    currentSchemeName: string | null;
    schemeAction: 'scored' | 'discarded' | 'held';
    nextSchemeName: string | null;
    nextSchemeModel: string | null;
    nextSchemeMarker: string | null;
    nextSchemeTerrain: string | null;
    submitting: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
}>();

const totalVp = computed(() => props.strategyPoints + props.schemePoints);

const actionLabel = computed(() => {
    if (props.schemeAction === 'scored') return 'Scored';
    if (props.schemeAction === 'discarded') return 'Discarded';
    return 'Held (hidden)';
});

const hasNextSetup = computed(() => !!(props.nextSchemeModel || props.nextSchemeMarker || props.nextSchemeTerrain));
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md">
            <DialogHeader>
                <DialogTitle>Confirm Turn {{ turnNumber }}</DialogTitle>
                <DialogDescription>
                    Lock in this turn's VP and scheme choice. Once submitted, the turn advances and can't be undone here.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3 text-sm">
                <!-- VP summary -->
                <div class="rounded-md border bg-muted/40 p-3">
                    <div class="mb-1.5 text-[10px] font-semibold uppercase text-muted-foreground">Score this turn</div>
                    <div class="flex items-center justify-between">
                        <span>
                            Strategy VP
                            <span v-if="strategyBonusUsed" class="ml-1 text-[10px] font-medium text-amber-700 dark:text-amber-400">
                                (uses once-per-game bonus)
                            </span>
                        </span>
                        <span class="font-mono font-semibold">{{ strategyPoints }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span>Scheme VP</span>
                        <span class="font-mono font-semibold">{{ schemePoints }}</span>
                    </div>
                    <div class="mt-1.5 flex items-center justify-between border-t pt-1.5">
                        <span class="font-semibold">Total</span>
                        <span class="font-mono text-base font-bold">{{ totalVp }} VP</span>
                    </div>
                </div>

                <!-- Current scheme + action -->
                <div class="rounded-md border p-3">
                    <div class="mb-1.5 text-[10px] font-semibold uppercase text-muted-foreground">Current scheme</div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="min-w-0 flex-1 font-medium">{{ currentSchemeName ?? '—' }}</span>
                        <span
                            class="shrink-0 rounded px-2 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400': schemeAction === 'scored',
                                'bg-red-500/15 text-red-700 dark:text-red-400': schemeAction === 'discarded',
                                'bg-muted text-muted-foreground': schemeAction === 'held',
                            }"
                        >
                            {{ actionLabel }}
                        </span>
                    </div>
                </div>

                <!-- Next scheme (if switching) -->
                <div v-if="nextSchemeName" class="rounded-md border border-primary/30 bg-primary/5 p-3">
                    <div class="mb-1.5 text-[10px] font-semibold uppercase text-muted-foreground">Next scheme</div>
                    <div class="font-medium">{{ nextSchemeName }}</div>
                    <div v-if="hasNextSetup" class="mt-1.5 space-y-0.5 text-xs text-muted-foreground">
                        <div v-if="nextSchemeModel">
                            Model: <span class="text-foreground">{{ nextSchemeModel }}</span>
                        </div>
                        <div v-if="nextSchemeMarker">
                            Marker: <span class="text-foreground">{{ nextSchemeMarker }}</span>
                        </div>
                        <div v-if="nextSchemeTerrain">
                            Terrain: <span class="text-foreground">{{ nextSchemeTerrain }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" :disabled="submitting" @click="emit('update:open', false)">Cancel</Button>
                <Button :disabled="submitting" @click="emit('confirm')">
                    <Loader2 v-if="submitting" class="mr-2 size-4 animate-spin" />
                    Submit Turn
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
