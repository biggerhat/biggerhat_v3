<script setup lang="ts">
import BlogContent from '@/components/blog/BlogContent.vue';
import CrewCard, { type ReportCrew } from '@/components/BattleReports/CrewCard.vue';
import GameCard, { type ReportGame } from '@/components/BattleReports/GameCard.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useConfirm } from '@/composables/useConfirm';
import { type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ThumbsUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';

/** One battle report: the game it is about (if any), then what the writer wrote. */
const props = defineProps<{
    report: {
        uuid: string;
        title: string;
        content: Record<string, unknown> | null;
        status: string;
        status_label: string;
        moderation_note: string | null;
        published_at: string | null;
        can_appeal: boolean;
        appeal_note: string | null;
    };
    author: { name: string; slug: string };
    game: ReportGame | null;
    crew: ReportCrew | null;
    comments: { id: number; body: string; date: string | null; author: { name: string; slug: string }; can_delete: boolean }[];
    allow_comments: boolean;
    can_comment: boolean;
    reactions: { count: number; mine: boolean };
    can_edit: boolean;
    /** A signed-in reader of someone else's public report who has not reported it yet. */
    can_flag: boolean;
    flagged: boolean;
    /** A moderator looking at a report that waits for approval. */
    can_moderate: boolean;
}>();

const confirm = useConfirm();
const comment = ref('');
const commentError = ref('');
const posting = ref(false);
function postComment() {
    posting.value = true;
    commentError.value = '';
    router.post(
        route('battle-reports.comment', props.report.uuid),
        { body: comment.value.trim() },
        {
            preserveScroll: true,
            onError: (e) => (commentError.value = e.body ?? 'Could not post that.'),
            onSuccess: () => (comment.value = ''),
            onFinish: () => (posting.value = false),
        },
    );
}
async function removeComment(id: number) {
    if (!(await confirm({ title: 'Remove this comment?', message: 'It is removed for good.', confirmLabel: 'Remove', destructive: true }))) return;
    router.delete(route('battle-reports.comment.destroy', [props.report.uuid, id]), { preserveScroll: true });
}
const react = () => router.post(route('battle-reports.react', props.report.uuid), {}, { preserveScroll: true });
const signedIn = computed(() => !!usePage<SharedData>().props.auth.user);
const open = ref<'appeal' | 'flag' | null>(null);
const text = ref('');
const sending = ref(false);
const errors = ref<Record<string, string>>({});
function send(kind: 'appeal' | 'flag') {
    sending.value = true;
    errors.value = {};
    router.post(
        route(kind === 'appeal' ? 'battle-reports.appeal' : 'battle-reports.flag', props.report.uuid),
        { [kind === 'appeal' ? 'note' : 'reason']: text.value.trim() },
        {
            preserveScroll: true,
            onError: (e) => (errors.value = e),
            onSuccess: () => {
                open.value = null;
                text.value = '';
            },
            onFinish: () => (sending.value = false),
        },
    );
}
async function remove() {
    if (
        !(await confirm({
            title: 'Delete this report?',
            message: 'The report is removed for good. The game it is about is not touched.',
            confirmLabel: 'Delete',
            destructive: true,
        }))
    )
        return;
    router.delete(route('battle-reports.destroy', props.report.uuid));
}
</script>

<template>
    <Head :title="report.title" />
    <PageBanner :title="report.title">
        <template #subtitle>
            <div class="my-auto flex flex-wrap items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                <span
                    >by <Link :href="route('stats.show', author.slug)" class="underline">{{ author.name }}</Link></span
                >
                <span v-if="report.published_at">· {{ report.published_at }}</span>
                <Badge v-if="can_edit && report.status !== 'approved'" variant="outline" data-testid="report-private">{{
                    report.status === 'private' ? 'Private: only you can see this' : report.status_label
                }}</Badge>
            </div>
        </template>
        <template v-if="can_edit" #actions>
            <div class="flex flex-wrap gap-2 px-2 pb-2 sm:pb-0">
                <Button as-child size="sm" variant="outline" class="max-sm:min-h-11" data-testid="report-edit">
                    <Link :href="route('battle-reports.edit', report.uuid)">Edit</Link>
                </Button>
                <Button size="sm" variant="outline" class="text-destructive max-sm:min-h-11" data-testid="report-delete" @click="remove"
                    >Delete</Button
                >
            </div>
        </template>
    </PageBanner>

    <article class="container mx-auto max-w-3xl space-y-4 px-4 pb-8 sm:px-6" data-testid="report-show">
        <p v-if="can_edit && report.status === 'pending'" class="rounded-md border bg-muted/30 p-3 text-sm" data-testid="report-pending-note">
            A moderator reads it before it is public. You are told when they decide; until then only you can see it.
        </p>
        <p
            v-if="can_edit && report.status === 'rejected'"
            class="rounded-md border border-destructive/40 bg-destructive/5 p-3 text-sm"
            data-testid="report-rejected-note"
        >
            A moderator did not approve this to be public<template v-if="report.moderation_note">: {{ report.moderation_note }}</template
            >. It is still yours. Change it and save to ask again<template v-if="report.can_appeal"
                >, or ask a moderator to look again without changing it</template
            >.
            <Button
                v-if="report.can_appeal && open !== 'appeal'"
                size="sm"
                variant="outline"
                class="ml-1 max-sm:min-h-11"
                data-testid="report-appeal-open"
                @click="open = 'appeal'"
                >Ask for a second look</Button
            >
        </p>
        <p v-if="can_edit && report.status === 'pending' && report.appeal_note" class="rounded-md border bg-muted/30 p-3 text-sm">
            You asked for a second look: “{{ report.appeal_note }}”
        </p>
        <form v-if="open === 'appeal'" class="space-y-2 rounded-md border p-3" data-testid="report-appeal-form" @submit.prevent="send('appeal')">
            <label for="appeal-note" class="text-sm font-medium">Why should a moderator look again?</label>
            <Textarea id="appeal-note" v-model="text" maxlength="500" rows="3" />
            <p v-if="errors.note" role="alert" class="text-sm text-destructive">{{ errors.note }}</p>
            <div class="flex gap-2">
                <Button type="submit" size="sm" class="max-sm:min-h-11" :disabled="sending || text.trim().length < 3" data-testid="report-appeal-send"
                    >Send</Button
                >
                <Button type="button" size="sm" variant="ghost" class="max-sm:min-h-11" @click="open = null">Cancel</Button>
            </div>
        </form>
        <div
            v-if="can_moderate"
            class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-primary/40 bg-primary/5 p-3 text-sm"
            data-testid="report-moderate"
        >
            <span>This report is waiting for approval to be public.</span>
            <Button as-child size="sm" variant="outline" class="max-sm:min-h-11"
                ><Link :href="route('admin.battle_reports.index')">Open the queue</Link></Button
            >
        </div>
        <GameCard v-if="game" :game="game" />
        <CrewCard v-if="crew" :crew="crew" />
        <BlogContent v-if="report.content" :content="report.content" />
        <p v-else class="text-sm text-muted-foreground">Nothing written yet.</p>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-muted-foreground">
            <Link :href="route('battle-reports.index')" class="underline">All battle reports</Link>
            <span v-if="flagged" data-testid="report-flagged">You reported this. A moderator will look at it.</span>
            <Button
                v-else-if="can_flag && open !== 'flag'"
                size="sm"
                variant="ghost"
                class="h-auto px-1 py-0.5 text-xs max-sm:min-h-11"
                data-testid="report-flag-open"
                @click="open = 'flag'"
                >Report this</Button
            >
        </div>
        <section
            v-if="report.status === 'approved'"
            id="comments"
            aria-labelledby="comments-h"
            class="space-y-3 border-t pt-4"
            data-testid="report-comments"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="comments-h" class="text-lg font-semibold">Comments ({{ comments.length }})</h2>
                <Button
                    v-if="signedIn && !can_edit"
                    size="sm"
                    :variant="reactions.mine ? 'default' : 'outline'"
                    class="max-sm:min-h-11"
                    :aria-pressed="reactions.mine"
                    data-testid="report-react"
                    @click="react"
                    ><ThumbsUp class="mr-1.5 size-4" /> Nice report · {{ reactions.count }}</Button
                >
                <span v-else class="text-sm text-muted-foreground" data-testid="report-react-count"
                    ><ThumbsUp class="mr-1 inline size-4" /> {{ reactions.count }}</span
                >
            </div>
            <ul v-if="comments.length" class="space-y-2">
                <li v-for="c in comments" :key="c.id" class="rounded-md border p-3 text-sm" data-testid="report-comment">
                    <div class="mb-1 flex items-center justify-between gap-2 text-xs text-muted-foreground">
                        <span
                            ><Link :href="route('stats.show', c.author.slug)" class="font-medium text-foreground hover:underline">{{
                                c.author.name
                            }}</Link>
                            · {{ c.date }}</span
                        >
                        <Button
                            v-if="c.can_delete"
                            size="sm"
                            variant="ghost"
                            class="h-auto px-1 py-0.5 text-xs text-destructive max-sm:min-h-11"
                            data-testid="report-comment-delete"
                            @click="removeComment(c.id)"
                            >Remove</Button
                        >
                    </div>
                    <p class="whitespace-pre-wrap break-words">{{ c.body }}</p>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">No comments yet.</p>
            <form v-if="can_comment" class="space-y-2" data-testid="report-comment-form" @submit.prevent="postComment">
                <label for="comment-body" class="text-sm font-medium">Add a comment</label>
                <Textarea id="comment-body" v-model="comment" maxlength="1000" rows="3" />
                <p v-if="commentError" role="alert" class="text-sm text-destructive">{{ commentError }}</p>
                <Button
                    type="submit"
                    size="sm"
                    class="max-sm:min-h-11"
                    :disabled="posting || comment.trim().length < 2"
                    data-testid="report-comment-send"
                    >Post comment</Button
                >
            </form>
            <p v-else-if="!allow_comments" class="text-xs text-muted-foreground">The writer has turned comments off.</p>
            <p v-else-if="!signedIn" class="text-xs text-muted-foreground">
                <Link :href="route('login')" class="underline">Sign in</Link> to comment.
            </p>
        </section>
        <form v-if="open === 'flag'" class="space-y-2 rounded-md border p-3" data-testid="report-flag-form" @submit.prevent="send('flag')">
            <label for="flag-reason" class="text-sm font-medium">What is wrong with this report?</label>
            <Textarea id="flag-reason" v-model="text" maxlength="300" rows="3" />
            <p v-if="errors.reason" role="alert" class="text-sm text-destructive">{{ errors.reason }}</p>
            <div class="flex gap-2">
                <Button type="submit" size="sm" class="max-sm:min-h-11" :disabled="sending || text.trim().length < 3" data-testid="report-flag-send"
                    >Send to the moderators</Button
                >
                <Button type="button" size="sm" variant="ghost" class="max-sm:min-h-11" @click="open = null">Cancel</Button>
            </div>
        </form>
    </article>
</template>
