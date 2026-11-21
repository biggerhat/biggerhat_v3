<script setup lang="ts">
/** The play screen's part of a tabletop game's Deployment step: where it stands, and the way to the table, where the crews are placed. */
import { Button } from '@/components/ui/button';
import { deployWords, type DeployPlayer, type DeployState } from '@/lib/tabletop/deployment';
import { Link } from '@inertiajs/vue3';
import { LayoutGrid } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ deploy: DeployState; players: DeployPlayer[]; gameUuid: string }>();
const words = computed(() => deployWords(props.deploy, props.players));
</script>

<template>
    <section class="mb-6 rounded-lg border bg-card p-4" aria-label="Deployment" data-testid="deploy-card">
        <div class="flex items-center gap-2">
            <LayoutGrid class="size-5 text-primary" aria-hidden="true" />
            <h2 class="text-lg font-semibold">Set up the table</h2>
        </div>
        <p class="mt-2 text-sm" role="status" aria-live="polite" data-testid="deploy-card-headline">{{ words.headline }}</p>
        <p class="mt-1 text-xs text-muted-foreground">
            The crews are placed on the tabletop, one group at a time, completely inside each player's deployment zone. Schemes come after.
        </p>
        <Button as-child class="mt-3 max-sm:min-h-11" :variant="words.yourTurn ? 'default' : 'outline'">
            <Link :href="route('games.board', gameUuid)" data-testid="deploy-open-table">{{
                words.yourTurn ? 'Go to the table: your turn' : 'Open the table'
            }}</Link>
        </Button>
    </section>
</template>
