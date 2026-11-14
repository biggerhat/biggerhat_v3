<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/vue3';
import { Loader2, Swords } from 'lucide-vue-next';
import { ref } from 'vue';

/** The invite link lands here: who is inviting, and one button that actually takes the seat (joining is a POST). */
const props = defineProps<{
    game: { uuid: string; name: string | null; encounter_size: number; season: string | null; format: string | null; creator_name: string | null };
}>();

const joining = ref(false);

const join = () => {
    if (joining.value) return;
    joining.value = true;
    router.post(route('games.join.confirm', props.game.uuid), {}, { onFinish: () => (joining.value = false) });
};
</script>

<template>
    <Head title="Join game" />
    <PageBanner title="Join game" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">You have been invited to a game</div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-lg px-4 pb-8" data-testid="join-game">
        <Card>
            <CardContent class="space-y-4 p-5">
                <div class="flex items-center gap-3">
                    <Swords class="size-8 shrink-0 text-primary" aria-hidden="true" />
                    <div class="min-w-0">
                        <h2 class="truncate text-lg font-semibold">{{ game.name || `${game.encounter_size}ss Encounter` }}</h2>
                        <p v-if="game.creator_name" class="text-sm text-muted-foreground">Hosted by {{ game.creator_name }}</p>
                    </div>
                </div>

                <dl class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-md border bg-muted/30 p-2">
                        <dt class="text-2xs uppercase text-muted-foreground">Size</dt>
                        <dd class="text-sm font-bold tabular-nums">{{ game.encounter_size }}ss</dd>
                    </div>
                    <div v-if="game.season" class="rounded-md border bg-muted/30 p-2">
                        <dt class="text-2xs uppercase text-muted-foreground">Season</dt>
                        <dd class="text-sm font-bold">{{ game.season }}</dd>
                    </div>
                    <div v-if="game.format" class="rounded-md border bg-muted/30 p-2">
                        <dt class="text-2xs uppercase text-muted-foreground">Format</dt>
                        <dd class="text-sm font-bold">{{ game.format }}</dd>
                    </div>
                </dl>

                <p class="text-xs text-muted-foreground">Attacker and Defender are assigned at random when you join.</p>

                <div class="flex flex-col gap-2 sm:flex-row-reverse">
                    <Button class="h-11 sm:h-9 sm:flex-1" data-testid="join-confirm" :disabled="joining" @click="join">
                        <Loader2 v-if="joining" class="mr-1.5 size-4 animate-spin" />
                        Join game
                    </Button>
                    <Button as-child variant="outline" class="h-11 sm:h-9">
                        <Link :href="route('games.index')">Not now</Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
