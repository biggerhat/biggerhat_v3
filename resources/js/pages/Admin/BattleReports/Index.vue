<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router } from '@inertiajs/vue3';
import { ScrollText } from 'lucide-vue-next';
import { ref } from 'vue';

type Status = 'pending' | 'reported' | 'approved' | 'rejected';

/** The queue of battle reports writers asked to make public: read each one, then approve it or turn it down (with a reason the writer sees). */
interface Row {
    uuid: string;
    title: string;
    excerpt: string;
    author: { name: string; slug: string };
    updated_at: string | null;
    moderated_at: string | null;
    moderated_by: string | null;
    auto_approved: boolean;
    note: string | null;
    appeal_note: string | null;
    flags: { reporter: string; reason: string }[];
    history: { decision: 'approved' | 'rejected'; by: string | null; note: string | null; date: string | null; overturned: boolean }[];
    url: string;
}
defineProps<{
    reports: Row[];
    status: Status;
    counts: Record<Status, number>;
}>();

const confirm = useConfirm();
const notes = ref<Record<string, string>>({});
const TABS: { key: Status; label: string }[] = [
    { key: 'pending', label: 'Waiting' },
    { key: 'reported', label: 'Reported' },
    { key: 'approved', label: 'Public' },
    { key: 'rejected', label: 'Not approved' },
];

const dismiss = (r: Row) => router.post(route('admin.battle_reports.dismiss_flags', r.uuid), {}, { preserveScroll: true });
const approve = (r: Row) => router.post(route('admin.battle_reports.approve', r.uuid), {}, { preserveScroll: true });
async function reject(r: Row, takeDown: boolean) {
    if (
        takeDown &&
        !(await confirm({
            title: `Take down "${r.title}"?`,
            message: 'It stops being public and the writer is told. They can change it and ask again.',
            confirmLabel: 'Take down',
            destructive: true,
        }))
    )
        return;
    router.post(route('admin.battle_reports.reject', r.uuid), { note: notes.value[r.uuid]?.trim() || null }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Battle Reports - Admin" />
    <PageBanner title="Battle Reports" class="mb-2">
        <template #logo>
            <div class="flex size-16 items-center justify-center md:size-20"><ScrollText class="size-8 text-primary md:size-10" /></div>
        </template>
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Reports writers asked to make public. Nothing is shown to anyone but its writer until you approve it.
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-4xl space-y-4 px-4 py-6 lg:px-8">
        <nav class="flex flex-wrap gap-2" aria-label="Report status">
            <Button v-for="t in TABS" :key="t.key" as-child size="sm" :variant="status === t.key ? 'default' : 'outline'" class="max-sm:min-h-11">
                <Link :href="route('admin.battle_reports.index', { status: t.key })" :data-testid="`reports-tab-${t.key}`"
                    >{{ t.label }} ({{ counts[t.key] }})</Link
                >
            </Button>
        </nav>

        <ul v-if="reports.length" class="space-y-3" data-testid="reports-queue">
            <li v-for="r in reports" :key="r.uuid">
                <Card>
                    <CardContent class="space-y-2 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <a :href="r.url" target="_blank" rel="noopener" class="font-semibold hover:underline">{{ r.title }}</a>
                            <Badge variant="outline" class="text-2xs">by {{ r.author.name }}</Badge>
                        </div>
                        <p v-if="r.excerpt" class="text-sm text-muted-foreground">{{ r.excerpt }}</p>
                        <p v-if="status === 'approved' || status === 'rejected'" class="text-xs text-muted-foreground">
                            {{ status === 'approved' ? 'Approved' : 'Turned down' }} {{ r.moderated_at
                            }}<template v-if="r.auto_approved"> automatically (a writer the moderators trust)</template
                            ><template v-else-if="r.moderated_by"> by {{ r.moderated_by }}</template
                            ><template v-if="r.note"> · “{{ r.note }}”</template>
                        </p>
                        <p v-if="r.appeal_note" class="rounded-md border border-primary/40 bg-primary/5 p-2 text-sm" data-testid="report-appeal-note">
                            <span class="font-medium">Asked for a second look:</span> “{{ r.appeal_note }}”
                            <template v-if="r.note"> (turned down before: “{{ r.note }}”)</template>
                        </p>
                        <ul
                            v-if="r.flags.length"
                            class="space-y-1 rounded-md border border-destructive/40 bg-destructive/5 p-2 text-sm"
                            data-testid="report-flags"
                        >
                            <li v-for="(f, i) in r.flags" :key="i">
                                <span class="font-medium">{{ f.reporter }}:</span> “{{ f.reason }}”
                            </li>
                        </ul>
                        <div v-if="status !== 'rejected'" class="flex flex-wrap items-center gap-2">
                            <Button v-if="status === 'pending'" size="sm" class="max-sm:min-h-11" :data-testid="`report-approve`" @click="approve(r)"
                                >Approve</Button
                            >
                            <Button
                                v-if="r.flags.length"
                                size="sm"
                                variant="outline"
                                class="max-sm:min-h-11"
                                data-testid="report-dismiss-flags"
                                @click="dismiss(r)"
                                >Leave it public</Button
                            >
                            <Input
                                v-model="notes[r.uuid]"
                                type="text"
                                maxlength="500"
                                placeholder="Reason, if you do not approve (the writer sees it)"
                                :aria-label="`Reason for ${r.title}`"
                                class="min-w-0 flex-1"
                            />
                            <Button
                                size="sm"
                                variant="outline"
                                class="text-destructive max-sm:min-h-11"
                                data-testid="report-reject"
                                @click="reject(r, status !== 'pending')"
                                >{{ status === 'pending' ? 'Do not approve' : 'Take down' }}</Button
                            >
                        </div>
                    </CardContent>
                </Card>
            </li>
        </ul>
        <p v-else class="text-sm text-muted-foreground" data-testid="reports-empty">Nothing here.</p>
    </div>
</template>
