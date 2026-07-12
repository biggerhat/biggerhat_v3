<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useNotifications } from '@/composables/useNotifications';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface NotificationRow {
    id: string;
    type: string;
    message: string;
    action_url: string | null;
    read_at: string | null;
    created_at: string;
}

const page = usePage<SharedData>();
const userId = computed(() => page.props.auth.user?.id ?? null);
const unreadCount = ref(page.props.unread_notifications_count);
watch(
    () => page.props.unread_notifications_count,
    (v) => (unreadCount.value = v),
);

useNotifications(userId.value, unreadCount);

const open = ref(false);
const notifications = ref<NotificationRow[]>([]);
const loading = ref(false);

watch(open, async (isOpen) => {
    if (!isOpen) return;
    loading.value = true;
    try {
        const res = await fetch(route('notifications.recent'), { headers: { Accept: 'application/json' } });
        if (res.ok) {
            const data = await res.json();
            notifications.value = data.notifications ?? [];
        }
    } finally {
        loading.value = false;
    }
});

const openNotification = async (n: NotificationRow) => {
    if (!n.read_at) {
        await fetch(route('notifications.read', n.id), { method: 'POST', headers: { 'X-CSRF-TOKEN': page.props.csrf_token as string } });
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    open.value = false;
    if (n.action_url) router.visit(n.action_url);
};

const markAllRead = async () => {
    await fetch(route('notifications.read-all'), { method: 'POST', headers: { 'X-CSRF-TOKEN': page.props.csrf_token as string } });
    notifications.value = notifications.value.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() }));
    unreadCount.value = 0;
};
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                aria-label="Notifications"
                class="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            >
                <Bell class="size-4" />
                <Badge
                    v-if="unreadCount > 0"
                    variant="destructive"
                    class="absolute -right-1 -top-1 h-4 min-w-4 justify-center rounded-full px-1 text-[10px] leading-none"
                >
                    {{ unreadCount > 9 ? '9+' : unreadCount }}
                </Badge>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent class="w-80" align="end" :side-offset="4">
            <div class="flex items-center justify-between px-2 py-1.5">
                <span class="text-sm font-medium">Notifications</span>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="text-xs text-primary hover:underline"
                    @click.stop="markAllRead"
                >
                    Mark all read
                </button>
            </div>
            <div class="max-h-96 overflow-y-auto">
                <EmptyState v-if="!loading && notifications.length === 0" compact title="No notifications yet" description="" />
                <DropdownMenuItem
                    v-for="n in notifications"
                    :key="n.id"
                    :class="['flex flex-col items-start gap-0.5 whitespace-normal py-2', !n.read_at ? 'bg-accent/50' : '']"
                    @select.prevent="openNotification(n)"
                >
                    <span class="text-sm">{{ n.message }}</span>
                    <span class="text-xs text-muted-foreground">{{ new Date(n.created_at).toLocaleString() }}</span>
                </DropdownMenuItem>
            </div>
            <Link
                :href="route('notifications.index')"
                class="block border-t px-2 py-1.5 text-center text-xs text-primary hover:underline"
                @click="open = false"
            >
                See all notifications
            </Link>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
