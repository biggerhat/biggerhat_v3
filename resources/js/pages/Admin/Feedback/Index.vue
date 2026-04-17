<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, router } from '@inertiajs/vue3';
import { Archive, BugPlay, CircleHelp, ExternalLink, Eye, Lightbulb, Loader2, Mail, Shield, Trash2, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface FeedbackEntry {
    id: number;
    user_id: number | null;
    name: string | null;
    email: string | null;
    category: string;
    subject: string | null;
    message: string;
    url: string | null;
    status: string;
    admin_notes: string | null;
    submitter_ip: string | null;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
}

interface Paginator<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    entries: Paginator<FeedbackEntry>;
    filters: { status: string | null; category: string | null };
    counts: { new: number; read: number; archived: number };
    statuses: { value: string; label: string }[];
    categories: { value: string; label: string }[];
}>();

const statusTone: Record<string, string> = {
    new: 'bg-primary/15 text-primary border-primary/30',
    read: 'bg-muted text-muted-foreground border-border',
    archived: 'bg-transparent text-muted-foreground border-dashed border-border',
};

const categoryIcon: Record<string, typeof BugPlay> = {
    bug: BugPlay,
    feature: Lightbulb,
    privacy: Shield,
    general: CircleHelp,
};

const categoryTone: Record<string, string> = {
    bug: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    feature: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    privacy: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
    general: 'bg-muted text-muted-foreground',
};

const formatDate = (iso: string) => {
    const d = new Date(iso);
    return d.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
};

// Which entries are currently in inline-edit mode for admin notes.
const editingNotes = ref<Record<number, string>>({});
const startEditingNotes = (entry: FeedbackEntry) => {
    editingNotes.value[entry.id] = entry.admin_notes ?? '';
};
const cancelEditingNotes = (id: number) => {
    delete editingNotes.value[id];
};

const applyStatus = (entry: FeedbackEntry, status: string) => {
    router.post(
        route('admin.feedback.update', entry.id),
        { status },
        { preserveScroll: true, preserveState: true },
    );
};

const saveNotes = (entry: FeedbackEntry) => {
    const notes = editingNotes.value[entry.id] ?? '';
    router.post(
        route('admin.feedback.update', entry.id),
        { admin_notes: notes },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => cancelEditingNotes(entry.id),
        },
    );
};

// Delete flow goes through a shadcn Dialog so the confirmation matches the
// rest of the app's visual language and plays nicely with the faction theme.
// `pendingDelete` holds the entry being confirmed; `deleting` tracks the
// in-flight request so the button can show a spinner + prevent double-click.
const pendingDelete = ref<FeedbackEntry | null>(null);
const deleting = ref(false);

const askDelete = (entry: FeedbackEntry) => {
    pendingDelete.value = entry;
};

const cancelDelete = () => {
    if (deleting.value) return;
    pendingDelete.value = null;
};

const confirmDelete = () => {
    if (!pendingDelete.value) return;
    const entry = pendingDelete.value;
    router.post(
        route('admin.feedback.delete', entry.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (deleting.value = true),
            onFinish: () => {
                deleting.value = false;
                pendingDelete.value = null;
            },
        },
    );
};

const activeStatus = computed(() => props.filters.status ?? 'all');
const activeCategory = computed(() => props.filters.category ?? 'all');

const setStatusFilter = (value: string) => {
    router.get(
        route('admin.feedback.index'),
        {
            ...(value === 'all' ? {} : { status: value }),
            ...(props.filters.category ? { category: props.filters.category } : {}),
        },
        { preserveScroll: true, preserveState: true },
    );
};

const setCategoryFilter = (value: string) => {
    router.get(
        route('admin.feedback.index'),
        {
            ...(props.filters.status ? { status: props.filters.status } : {}),
            ...(value === 'all' ? {} : { category: value }),
        },
        { preserveScroll: true, preserveState: true },
    );
};
</script>

<template>
    <Head title="Feedback — Admin" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Feedback Inbox" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    User-submitted bug reports, feature requests, and privacy questions.
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto max-w-5xl px-4 sm:px-6">
            <!-- Status filter pills with counts -->
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <button
                    :class="[
                        'rounded-full border px-3 py-1 text-xs transition-colors',
                        activeStatus === 'all' ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                    ]"
                    @click="setStatusFilter('all')"
                >
                    All
                </button>
                <button
                    v-for="s in statuses"
                    :key="s.value"
                    :class="[
                        'flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs transition-colors',
                        activeStatus === s.value ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                    ]"
                    @click="setStatusFilter(s.value)"
                >
                    {{ s.label }}
                    <Badge variant="outline" class="border-transparent bg-background/60 px-1 py-0 text-[10px] tabular-nums">
                        {{ counts[s.value as 'new' | 'read' | 'archived'] ?? 0 }}
                    </Badge>
                </button>

                <div class="ml-auto flex items-center gap-2">
                    <Select :model-value="activeCategory" @update:model-value="(v) => setCategoryFilter(v as string)">
                        <SelectTrigger class="h-8 w-44 text-xs"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All categories</SelectItem>
                            <SelectItem v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <!-- Empty state -->
            <Card v-if="!entries.data.length" class="py-16 text-center">
                <CardContent>
                    <Mail class="mx-auto mb-4 size-10 text-muted-foreground/40" />
                    <p class="mb-1 text-sm font-semibold">Nothing here</p>
                    <p class="text-xs text-muted-foreground">No feedback matches the current filters.</p>
                </CardContent>
            </Card>

            <!-- Feedback cards -->
            <div v-else class="space-y-3">
                <Card v-for="entry in entries.data" :key="entry.id">
                    <CardContent class="p-4">
                        <!-- Header row -->
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <Badge
                                :class="['border text-[10px] uppercase tracking-wide', statusTone[entry.status]]"
                                variant="outline"
                            >
                                {{ entry.status }}
                            </Badge>
                            <Badge :class="['flex items-center gap-1 border-0 text-[10px]', categoryTone[entry.category]]" variant="outline">
                                <component :is="categoryIcon[entry.category] ?? CircleHelp" class="size-3" />
                                {{ entry.category }}
                            </Badge>
                            <span class="ml-auto text-[11px] tabular-nums text-muted-foreground">{{ formatDate(entry.created_at) }}</span>
                        </div>

                        <!-- Subject + body -->
                        <h3 v-if="entry.subject" class="mb-1 font-semibold">{{ entry.subject }}</h3>
                        <p class="whitespace-pre-wrap text-sm leading-relaxed">{{ entry.message }}</p>

                        <!-- Attribution + context -->
                        <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted-foreground">
                            <span v-if="entry.user" class="inline-flex items-center gap-1">
                                <User class="size-3" />
                                <Link :href="route('admin.users.edit', entry.user.id)" class="hover:underline">{{ entry.user.name }}</Link>
                                <span>·</span>
                                <a :href="`mailto:${entry.user.email}`" class="hover:underline">{{ entry.user.email }}</a>
                            </span>
                            <span v-else-if="entry.name || entry.email" class="inline-flex items-center gap-1">
                                <User class="size-3" />
                                <span v-if="entry.name">{{ entry.name }}</span>
                                <a v-if="entry.email" :href="`mailto:${entry.email}`" class="hover:underline">{{ entry.email }}</a>
                            </span>
                            <span v-else class="italic text-muted-foreground">Anonymous</span>

                            <a v-if="entry.url" :href="entry.url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 hover:underline">
                                <ExternalLink class="size-3" /> Submitted from
                            </a>
                            <span v-if="entry.submitter_ip" class="font-mono text-[10px]">{{ entry.submitter_ip }}</span>
                        </div>

                        <!-- Admin notes (inline editor) -->
                        <div class="mt-3 rounded-md border border-dashed bg-muted/30 p-3">
                            <div class="mb-1 flex items-center justify-between">
                                <span class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Admin notes (private)</span>
                                <Button v-if="!(entry.id in editingNotes)" variant="ghost" size="sm" class="h-6 px-2 text-[10px]" @click="startEditingNotes(entry)">
                                    {{ entry.admin_notes ? 'Edit' : 'Add note' }}
                                </Button>
                            </div>
                            <template v-if="entry.id in editingNotes">
                                <Textarea v-model="editingNotes[entry.id]" rows="3" class="text-xs" placeholder="Notes only you and other admins see" />
                                <div class="mt-2 flex gap-2">
                                    <Button size="sm" class="h-7 text-[11px]" @click="saveNotes(entry)">Save</Button>
                                    <Button variant="ghost" size="sm" class="h-7 text-[11px]" @click="cancelEditingNotes(entry.id)">Cancel</Button>
                                </div>
                            </template>
                            <p v-else-if="entry.admin_notes" class="whitespace-pre-wrap text-xs text-muted-foreground">{{ entry.admin_notes }}</p>
                            <p v-else class="text-xs italic text-muted-foreground">No notes yet.</p>
                        </div>

                        <!-- Actions -->
                        <div class="mt-3 flex flex-wrap items-center gap-2 border-t pt-3">
                            <Button
                                v-if="entry.status !== 'read'"
                                size="sm"
                                variant="outline"
                                class="h-7 gap-1 text-[11px]"
                                @click="applyStatus(entry, 'read')"
                            >
                                <Eye class="size-3" /> Mark read
                            </Button>
                            <Button
                                v-if="entry.status !== 'new'"
                                size="sm"
                                variant="outline"
                                class="h-7 gap-1 text-[11px]"
                                @click="applyStatus(entry, 'new')"
                            >
                                Reopen
                            </Button>
                            <Button
                                v-if="entry.status !== 'archived'"
                                size="sm"
                                variant="outline"
                                class="h-7 gap-1 text-[11px]"
                                @click="applyStatus(entry, 'archived')"
                            >
                                <Archive class="size-3" /> Archive
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="ml-auto h-7 gap-1 text-[11px] text-destructive hover:bg-destructive/10 hover:text-destructive"
                                @click="askDelete(entry)"
                            >
                                <Trash2 class="size-3" /> Delete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Pagination -->
            <div v-if="entries.last_page > 1" class="mt-6 flex flex-wrap justify-center gap-1">
                <Link
                    v-for="link in entries.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    preserve-scroll
                    preserve-state
                    :class="[
                        'min-w-8 rounded-md border px-3 py-1.5 text-center text-xs transition-colors',
                        link.active ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:bg-accent',
                        !link.url ? 'pointer-events-none opacity-40' : '',
                    ]"
                >
                    <!-- Laravel's paginator escapes its labels server-side (« / »);
                         we render via span so ESLint's no-v-html-on-component rule
                         accepts it. -->
                    <span v-html="link.label" />
                </Link>
            </div>
        </div>
    </div>

    <!-- Delete confirmation -->
    <Dialog :open="pendingDelete !== null" @update:open="(v) => { if (!v) cancelDelete(); }">
        <DialogContent class="max-w-sm">
            <DialogHeader>
                <DialogTitle>Delete feedback entry?</DialogTitle>
                <DialogDescription>
                    This permanently removes the message and any admin notes attached to it. You can't undo this.
                </DialogDescription>
            </DialogHeader>
            <div v-if="pendingDelete" class="rounded-md border bg-muted/30 p-3 text-xs">
                <p v-if="pendingDelete.subject" class="mb-1 font-semibold">{{ pendingDelete.subject }}</p>
                <p class="line-clamp-3 whitespace-pre-wrap text-muted-foreground">{{ pendingDelete.message }}</p>
            </div>
            <DialogFooter class="gap-2 sm:gap-0">
                <Button variant="outline" :disabled="deleting" @click="cancelDelete">Cancel</Button>
                <Button variant="destructive" :disabled="deleting" @click="confirmDelete">
                    <Loader2 v-if="deleting" class="mr-2 size-4 animate-spin" />
                    <Trash2 v-else class="mr-2 size-4" />
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
