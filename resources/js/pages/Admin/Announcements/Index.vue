<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router } from '@inertiajs/vue3';
import { Megaphone, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const confirm = useConfirm();

interface AnnouncementRow {
    id: number;
    message: string;
    level: string;
    audience: string;
    starts_at: string | null;
    ends_at: string | null;
    is_dismissable: boolean;
    link_url: string | null;
    link_label: string | null;
    created_by: { id: number; name: string } | null;
    created_at: string | null;
}

const props = defineProps<{ announcements: AnnouncementRow[] }>();

const form = ref({
    message: '',
    level: 'info',
    audience: 'all',
    starts_at: '',
    ends_at: '',
    is_dismissable: true,
    link_url: '',
    link_label: '',
});

const reset = () => {
    form.value = {
        message: '',
        level: 'info',
        audience: 'all',
        starts_at: '',
        ends_at: '',
        is_dismissable: true,
        link_url: '',
        link_label: '',
    };
};

const submit = () => {
    if (!form.value.message) return;
    router.post(
        route('admin.announcements.store'),
        {
            ...form.value,
            starts_at: form.value.starts_at || null,
            ends_at: form.value.ends_at || null,
            link_url: form.value.link_url || null,
            link_label: form.value.link_label || null,
        },
        { preserveScroll: true, onSuccess: reset },
    );
};

const remove = async (a: AnnouncementRow) => {
    if (!(await confirm({
        title: 'Delete announcement',
        message: `Delete this announcement?\n\n"${a.message.slice(0, 200)}"`,
        confirmLabel: 'Delete',
        destructive: true,
    }))) return;
    router.post(route('admin.announcements.delete', a.id), { _method: 'DELETE' }, { preserveScroll: true });
};

const formatDate = (s: string | null) => (s ? new Date(s).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : null);

const levelBadgeClass = (level: string) => {
    switch (level) {
        case 'warning':
            return 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-400';
        case 'success':
            return 'border-green-500/40 bg-green-500/10 text-green-700 dark:text-green-400';
        default:
            return 'border-blue-500/40 bg-blue-500/10 text-blue-700 dark:text-blue-400';
    }
};

const isActiveNow = (a: AnnouncementRow): boolean => {
    const now = new Date().getTime();
    const startsOk = !a.starts_at || new Date(a.starts_at).getTime() <= now;
    const endsOk = !a.ends_at || new Date(a.ends_at).getTime() > now;
    return startsOk && endsOk;
};

const sorted = computed(() =>
    [...props.announcements].sort((a, b) => Number(isActiveNow(b)) - Number(isActiveNow(a))),
);
</script>

<template>
    <Head title="Announcements - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex items-center gap-2">
            <Megaphone class="size-5" />
            <h1 class="text-2xl font-semibold tracking-tight">Announcements</h1>
        </div>
        <p class="text-sm text-muted-foreground">
            Site-wide banners pushed via the shared Inertia data. Active when current time is within
            <code class="rounded bg-muted px-1 text-xs">starts_at</code> / <code class="rounded bg-muted px-1 text-xs">ends_at</code>; both fields are optional.
        </p>

        <Card>
            <CardContent class="space-y-3 p-4">
                <div class="text-sm font-semibold">New announcement</div>
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="md:col-span-2 flex flex-col gap-1">
                        <Label for="message">Message</Label>
                        <Textarea id="message" v-model="form.message" rows="2" placeholder="Heads-up to everyone…" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label>Level</Label>
                        <Select v-model="form.level">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="info">Info (blue)</SelectItem>
                                <SelectItem value="success">Success (green)</SelectItem>
                                <SelectItem value="warning">Warning (amber)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label>Audience</Label>
                        <Select v-model="form.audience">
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Everyone</SelectItem>
                                <SelectItem value="authenticated">Authenticated only</SelectItem>
                                <SelectItem value="super_admin">Super admin only</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label for="starts_at">Starts at (optional)</Label>
                        <Input id="starts_at" v-model="form.starts_at" type="datetime-local" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label for="ends_at">Ends at (optional)</Label>
                        <Input id="ends_at" v-model="form.ends_at" type="datetime-local" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label for="link_url">Link URL (optional)</Label>
                        <Input id="link_url" v-model="form.link_url" placeholder="https://…" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <Label for="link_label">Link label (optional)</Label>
                        <Input id="link_label" v-model="form.link_label" placeholder="Learn more" />
                    </div>
                    <div class="flex items-center gap-2 md:col-span-2">
                        <Checkbox id="dismissable" v-model:checked="form.is_dismissable" />
                        <Label for="dismissable">Dismissable by visitor</Label>
                    </div>
                </div>
                <div class="flex justify-end">
                    <Button :disabled="!form.message" @click="submit">Publish</Button>
                </div>
            </CardContent>
        </Card>

        <div class="space-y-2">
            <Card v-for="a in sorted" :key="a.id">
                <CardContent class="flex items-start gap-3 p-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" :class="levelBadgeClass(a.level)">{{ a.level }}</Badge>
                            <Badge variant="secondary" class="text-[10px]">{{ a.audience }}</Badge>
                            <Badge v-if="isActiveNow(a)" class="border-green-500/40 bg-green-500/10 text-[10px] text-green-700 dark:text-green-400">live</Badge>
                            <Badge v-else variant="outline" class="text-[10px] text-muted-foreground">scheduled / expired</Badge>
                        </div>
                        <p class="mt-1 text-sm">{{ a.message }}</p>
                        <div class="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground">
                            <span v-if="a.starts_at">starts {{ formatDate(a.starts_at) }}</span>
                            <span v-if="a.ends_at">ends {{ formatDate(a.ends_at) }}</span>
                            <span v-if="a.link_url">→ {{ a.link_label ?? a.link_url }}</span>
                            <span v-if="a.created_by">by {{ a.created_by.name }}</span>
                        </div>
                    </div>
                    <Button variant="outline" size="sm" @click="remove(a)">
                        <Trash2 class="size-3.5" />
                    </Button>
                </CardContent>
            </Card>
            <div v-if="!sorted.length" class="py-8 text-center text-sm text-muted-foreground">No announcements yet.</div>
        </div>
    </div>
</template>
