<script setup lang="ts">
import HelpButton from '@/components/Help/HelpButton.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Head, Link, router } from '@inertiajs/vue3';
import type { RequestPayload } from '@inertiajs/core';
import { computed, defineAsyncComponent, ref } from 'vue';

/**
 * Write or edit a battle report. The text is the same editor the articles use. A report may be about one of your finished games
 * (tracked or logged) or stand alone. The server (BattleReports\StoreBattleReportRequest) is the judge; this only helps.
 */
const TipTapEditor = defineAsyncComponent(() => import('@/components/blog/TipTapEditor.vue'));

const props = defineProps<{
    report: {
        uuid: string;
        title: string;
        content: Record<string, unknown> | null;
        is_public: boolean;
        show_game: boolean;
        allow_comments: boolean;
        status: string;
        status_label: string;
        moderation_note: string | null;
        game_uuid: string | null;
        crew_id: number | null;
    } | null;
    crews: { id: number; label: string }[];
    games: { uuid: string; label: string; played_on: string | null; origin: string; taken: boolean }[];
    preselected: string | null;
    suggested_title: string;
}>();

const NONE = '__none';
const editing = computed(() => props.report !== null);
const title = ref(props.report?.title ?? props.suggested_title);
const content = ref<Record<string, unknown> | null>(props.report?.content ?? null);
const isPublic = ref(props.report?.is_public ?? false);
const showGame = ref(props.report?.show_game ?? true);
const allowComments = ref(props.report?.allow_comments ?? true);
const crewId = ref<string>(props.report?.crew_id ? String(props.report.crew_id) : NONE);
const gameUuid = ref<string>(props.report?.game_uuid ?? props.preselected ?? NONE);
const errors = ref<Record<string, string>>({});
const saving = ref(false);

const gameLabel = (g: { label: string; played_on: string | null; taken: boolean }) =>
    `${g.label}${g.played_on ? ' · ' + g.played_on : ''}${g.taken ? ' (has a report)' : ''}`;

function onGameChange(uuid: unknown) {
    gameUuid.value = String(uuid);
    // A new report with no title yet takes the game's name.
    const g = props.games.find((x) => x.uuid === uuid);
    if (g && !title.value.trim()) title.value = g.label;
}

function save() {
    saving.value = true;
    errors.value = {};
    const payload = {
        title: title.value.trim(),
        content: content.value,
        is_public: isPublic.value,
        show_game: showGame.value,
        allow_comments: allowComments.value,
        game_uuid: gameUuid.value === NONE || gameUuid.value.startsWith('round:') ? null : gameUuid.value,
        round_id: gameUuid.value.startsWith('round:') ? Number(gameUuid.value.slice(6)) : null,
        crew_id: crewId.value === NONE ? null : Number(crewId.value),
    } as unknown as RequestPayload;
    const opts = {
        preserveScroll: true,
        onError: (e: Record<string, string>) => (errors.value = e),
        onFinish: () => (saving.value = false),
    };
    if (props.report) router.put(route('battle-reports.update', props.report.uuid), payload, opts);
    else router.post(route('battle-reports.store'), payload, opts);
}
</script>

<template>
    <Head :title="editing ? 'Edit battle report' : 'Write a battle report'" />
    <PageBanner :title="editing ? 'Edit battle report' : 'Write a battle report'">
        <template #subtitle>
            <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Tell the story of a game. Only you can see it until you make it public.
                <HelpButton topic="game-tracker" section="reports" />
            </div>
        </template>
    </PageBanner>

    <form class="container mx-auto max-w-3xl space-y-4 px-4 pb-24 sm:px-6 sm:pb-8" data-testid="report-form" @submit.prevent="save">
        <div
            v-if="Object.keys(errors).length"
            role="alert"
            class="rounded-md border border-destructive/50 bg-destructive/10 p-3 text-sm"
            data-testid="report-errors"
        >
            <p class="font-medium">Check these:</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="(m, k) in errors" :key="k">{{ m }}</li>
            </ul>
        </div>

        <Card>
            <CardContent class="space-y-4 p-4">
                <div class="space-y-1.5">
                    <Label for="report-title">Title</Label>
                    <Input
                        id="report-title"
                        v-model="title"
                        type="text"
                        maxlength="160"
                        placeholder="The night the Ten Thunders held the line"
                        data-testid="report-title"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label for="report-game">The game it is about</Label>
                    <Select :model-value="gameUuid" @update:model-value="onGameChange">
                        <SelectTrigger id="report-game" data-testid="report-game-select"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">No game: a story on its own</SelectItem>
                            <SelectItem v-for="g in games" :key="g.uuid" :value="g.uuid" :disabled="g.taken">{{ gameLabel(g) }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Your finished games, from the tracker or logged by hand, and rounds you played in finished tournaments. The report shows the
                        players, factions and score above your text.
                        <Link :href="route('games.logged.create')" class="underline">Log a game</Link> first if it is not here.
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="report-crew">The crew you played</Label>
                    <Select v-model="crewId">
                        <SelectTrigger id="report-crew" data-testid="report-crew-select"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NONE">No crew list</SelectItem>
                            <SelectItem v-for="c in crews" :key="c.id" :value="String(c.id)">{{ c.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        One of your crew lists from the Crew Builder. Readers see the master and models, not the crew's name or notes. It follows the
                        list: change the list and the report changes with it.
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardContent class="space-y-2 p-4">
                <Label>The report</Label>
                <TipTapEditor
                    v-model="content"
                    :entities="false"
                    :upload-image-route="route('battle-reports.image')"
                    placeholder="How the game went: deployment, the turning point, what you would do differently…"
                    aria-label="Battle report"
                />
            </CardContent>
        </Card>

        <Card>
            <CardContent class="space-y-4 p-4">
                <div class="flex items-start gap-3">
                    <Switch id="report-show-game" v-model="showGame" :disabled="gameUuid === NONE" data-testid="report-show-game" />
                    <div>
                        <Label for="report-show-game">Show the game's scoreboard above the report</Label>
                        <p class="text-xs text-muted-foreground">
                            The players, factions, masters and points of the game. Off, readers see only your text. It needs a game picked above.
                        </p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <Switch id="report-comments" v-model="allowComments" data-testid="report-comments" />
                    <div>
                        <Label for="report-comments">Let readers comment</Label>
                        <p class="text-xs text-muted-foreground">
                            Once the report is public, signed-in readers can comment under it. You can remove any comment, and so can the moderators.
                        </p>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <Switch id="report-public" v-model="isPublic" data-testid="report-public" />
                    <div>
                        <Label for="report-public">Ask to make this report public</Label>
                        <p class="text-xs text-muted-foreground">
                            Off, it is only yours. On, a moderator reads it first; once approved it is listed on the Battle reports page and anyone
                            with the link can read it. Fixing typos or adding a few words keeps an approved report public. A new image or link, a new
                            title or game, or a lot of new text sends it back to a moderator, and it is hidden until they approve it again.
                        </p>
                        <p v-if="report && report.status !== 'private'" class="mt-1 text-xs font-medium" data-testid="report-status">
                            Right now: {{ report.status_label }}<template v-if="report.moderation_note"> · {{ report.moderation_note }}</template>
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div
            class="sticky bottom-14 z-10 -mx-4 flex items-center justify-between gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0"
        >
            <Button as-child variant="ghost" class="max-sm:min-h-11">
                <Link :href="report ? route('battle-reports.show', report.uuid) : route('battle-reports.index')">Cancel</Link>
            </Button>
            <Button type="submit" class="min-h-11 sm:min-h-9" :disabled="saving || !title.trim()" data-testid="report-save">{{
                saving ? 'Saving…' : 'Save report'
            }}</Button>
        </div>
    </form>
</template>
