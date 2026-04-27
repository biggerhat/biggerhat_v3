<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router } from '@inertiajs/vue3';
import { Globe, LogOut, MonitorSmartphone } from 'lucide-vue-next';
import { computed } from 'vue';

const confirm = useConfirm();

interface Session {
    id: string;
    user: { id: number; name: string; email: string } | null;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number | null;
    last_activity_iso: string | null;
    is_current: boolean;
}

const props = defineProps<{ sessions: Session[] }>();

// Group by user so the UI can collapse "Bob: 3 sessions" into one expandable row.
const grouped = computed(() => {
    const map = new Map<string, { user: Session['user']; sessions: Session[] }>();
    for (const s of props.sessions) {
        const key = s.user ? `u:${s.user.id}` : 'guest';
        if (!map.has(key)) map.set(key, { user: s.user, sessions: [] });
        map.get(key)!.sessions.push(s);
    }
    return Array.from(map.values());
});

const formatTime = (s: string | null) => (s ? new Date(s).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' }) : '—');

const shortenAgent = (ua: string | null) => {
    if (!ua) return 'Unknown';
    // Quick browser/OS sniff — exact UA is preserved as title for hover detail.
    const browser = /Firefox\/[\d.]+/.exec(ua)?.[0] ?? /Chrome\/[\d.]+/.exec(ua)?.[0] ?? /Safari\/[\d.]+/.exec(ua)?.[0] ?? 'Other';
    const os = /Mac OS X [\d_.]+/.exec(ua)?.[0] ?? /Windows NT [\d.]+/.exec(ua)?.[0] ?? /Android [\d.]+/.exec(ua)?.[0] ?? /iPhone|iPad/.exec(ua)?.[0] ?? '';
    return `${browser}${os ? ' · ' + os : ''}`;
};

const revokeSession = async (s: Session) => {
    if (s.is_current) {
        if (!(await confirm({
            title: 'Revoke your own session?',
            message: 'This will log YOU out immediately.',
            confirmLabel: 'Log myself out',
            destructive: true,
        }))) return;
    } else if (!(await confirm({
        title: 'Revoke session',
        message: 'The user will be logged out on their next request.',
        confirmLabel: 'Revoke',
        destructive: true,
    }))) {
        return;
    }
    router.post(route('admin.sessions.delete', s.id), {}, { preserveScroll: true });
};

const revokeAllForUser = async (userId: number) => {
    if (!(await confirm({
        title: 'Revoke all sessions',
        message: 'They will be logged out everywhere.',
        confirmLabel: 'Revoke all',
        destructive: true,
    }))) return;
    router.post(route('admin.sessions.delete_all_for_user', userId), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Active Sessions - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex flex-wrap items-center gap-2">
            <MonitorSmartphone class="size-5" />
            <h1 class="text-2xl font-semibold tracking-tight">Active Sessions</h1>
            <Badge variant="secondary">{{ sessions.length }}</Badge>
        </div>
        <p class="text-sm text-muted-foreground">Database-stored sessions. Revoking a session forces a logout on the next request.</p>

        <div class="space-y-3">
            <Card v-for="group in grouped" :key="group.user?.id ?? 'guest'">
                <CardContent class="space-y-2 p-4">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <span v-if="group.user" class="font-semibold">{{ group.user.name }}</span>
                            <span v-else class="font-semibold text-muted-foreground">Guest sessions</span>
                            <span v-if="group.user" class="ml-1 text-xs text-muted-foreground">{{ group.user.email }}</span>
                            <Badge variant="secondary" class="ml-2 text-[10px]">{{ group.sessions.length }} active</Badge>
                        </div>
                        <Button v-if="group.user" variant="outline" size="sm" @click="revokeAllForUser(group.user.id)">
                            <LogOut class="mr-1.5 size-3.5" /> Revoke all
                        </Button>
                    </div>
                    <div class="space-y-1.5">
                        <div
                            v-for="s in group.sessions"
                            :key="s.id"
                            class="flex flex-col gap-1.5 rounded-md border px-3 py-2 text-xs sm:flex-row sm:items-center sm:gap-3"
                            :class="s.is_current ? 'border-primary/40 bg-primary/5' : ''"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-2">
                                <Globe class="size-3.5 shrink-0 text-muted-foreground" />
                                <span class="truncate font-mono">{{ s.ip_address ?? '—' }}</span>
                                <span class="truncate text-muted-foreground" :title="s.user_agent ?? ''">{{ shortenAgent(s.user_agent) }}</span>
                                <Badge v-if="s.is_current" variant="outline" class="text-[9px]">you</Badge>
                            </div>
                            <span class="shrink-0 text-muted-foreground">{{ formatTime(s.last_activity_iso) }}</span>
                            <Button variant="ghost" size="sm" @click="revokeSession(s)">
                                <LogOut class="size-3.5" />
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
            <div v-if="!sessions.length" class="py-12 text-center text-sm text-muted-foreground">No active sessions.</div>
        </div>
    </div>
</template>
