<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Loader2, Plus, X } from 'lucide-vue-next';

/** Create or edit a league: its name, how finishing places turn into points, and whether only the best events count. */
const props = defineProps<{
    series: {
        uuid: string;
        name: string;
        description: string | null;
        points_table: number[];
        participation_points: number;
        best_of: number | null;
    } | null;
    default_points: number[];
}>();

const form = useForm({
    name: props.series?.name ?? '',
    description: props.series?.description ?? '',
    points: [...(props.series?.points_table ?? props.default_points)] as (number | string)[],
    participation_points: props.series?.participation_points ?? 1,
    best_of: (props.series?.best_of ?? '') as number | '',
});

const ordinal = (n: number) =>
    n % 10 === 1 && n % 100 !== 11 ? `${n}st` : n % 10 === 2 && n % 100 !== 12 ? `${n}nd` : n % 10 === 3 && n % 100 !== 13 ? `${n}rd` : `${n}th`;
const addPlace = () => form.points.length < 16 && form.points.push(0);
const dropPlace = (i: number) => form.points.length > 1 && form.points.splice(i, 1);

const submit = () => {
    form.transform((d) => ({
        ...d,
        description: d.description.trim() || null,
        best_of: d.best_of === '' ? null : d.best_of,
        points: d.points.map((p) => Number(p) || 0),
    }));
    if (props.series) form.put(route('tournaments.series.update', props.series.uuid));
    else form.post(route('tournaments.series.store'));
};
</script>

<template>
    <Head :title="series ? 'Edit league' : 'New league'" />
    <PageBanner :title="series ? 'Edit league' : 'New league'" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                One leaderboard across several events
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto sm:px-4">
        <Button
            variant="ghost"
            class="mb-4 gap-1.5 text-sm"
            @click="router.get(series ? route('tournaments.series.show', series.uuid) : route('tournaments.series.index'))"
        >
            <ArrowLeft class="size-4" /> Back
        </Button>

        <form class="mx-auto max-w-lg" @submit.prevent="submit">
            <Card>
                <CardContent class="space-y-5 p-6">
                    <div class="space-y-2">
                        <Label for="s-name">League name</Label>
                        <Input id="s-name" v-model="form.name" placeholder="e.g. Winter Ladder 2026" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="space-y-2">
                        <Label for="s-desc">Description <span class="text-xs text-muted-foreground">(optional)</span></Label>
                        <Textarea id="s-desc" v-model="form.description" rows="2" placeholder="Who can play, how often, what it's for…" />
                        <InputError :message="form.errors.description" />
                    </div>

                    <fieldset class="space-y-2">
                        <legend class="text-sm font-medium">Points for each finishing place</legend>
                        <p class="text-xs text-muted-foreground">
                            Every finished event in the league awards these. Joint places share the points of their place.
                        </p>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div v-for="(_, i) in form.points" :key="i" class="relative">
                                <Label :for="`s-p-${i}`" class="text-2xs text-muted-foreground">{{ ordinal(i + 1) }}</Label>
                                <Input :id="`s-p-${i}`" v-model="form.points[i]" type="number" min="0" max="1000" class="pr-7" />
                                <button
                                    v-if="form.points.length > 1 && i === form.points.length - 1"
                                    type="button"
                                    class="absolute bottom-2 right-2 text-muted-foreground hover:text-destructive"
                                    :aria-label="`Remove ${ordinal(i + 1)} place`"
                                    @click="dropPlace(i)"
                                >
                                    <X class="size-3.5" />
                                </button>
                            </div>
                        </div>
                        <Button type="button" variant="outline" size="sm" class="gap-1 text-xs" :disabled="form.points.length >= 16" @click="addPlace"
                            ><Plus class="size-3.5" /> Add a place</Button
                        >
                        <InputError :message="form.errors.points" />
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="s-part">Participation points</Label>
                            <Input id="s-part" v-model.number="form.participation_points" type="number" min="0" max="100" />
                            <p class="text-2xs text-muted-foreground">For anyone who played but finished below the table.</p>
                            <InputError :message="form.errors.participation_points" />
                        </div>
                        <div class="space-y-2">
                            <Label for="s-best">Best events that count <span class="text-xs text-muted-foreground">(optional)</span></Label>
                            <Input id="s-best" v-model.number="form.best_of" type="number" min="1" max="20" placeholder="All events" />
                            <p class="text-2xs text-muted-foreground">Leave empty to add up every event.</p>
                            <InputError :message="form.errors.best_of" />
                        </div>
                    </div>

                    <Button type="submit" class="w-full" :disabled="form.processing || !form.name.trim()">
                        <Loader2 v-if="form.processing" class="mr-2 size-4 animate-spin" /> {{ series ? 'Save league' : 'Create league' }}
                    </Button>
                </CardContent>
            </Card>
        </form>
    </div>
</template>
