<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Head, router, usePage } from '@inertiajs/vue3';
import { type SharedData } from '@/types';

interface NotificationRow {
    id: string;
    type: string;
    message: string;
    action_url: string | null;
    read_at: string | null;
    created_at: string;
}

interface Paginator {
    data: NotificationRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    from: number | null;
    to: number | null;
}

const props = defineProps<{ notifications: Paginator }>();
const page = usePage<SharedData>();

const openNotification = async (n: NotificationRow) => {
    if (!n.read_at) {
        await fetch(route('notifications.read', n.id), { method: 'POST', headers: { 'X-CSRF-TOKEN': page.props.csrf_token as string } });
        router.reload({ only: ['notifications', 'unread_notifications_count'] });
    }
    if (n.action_url) router.visit(n.action_url);
};

const markAllRead = async () => {
    await fetch(route('notifications.read-all'), { method: 'POST', headers: { 'X-CSRF-TOKEN': page.props.csrf_token as string } });
    router.reload({ only: ['notifications', 'unread_notifications_count'] });
};
</script>

<template>
    <Head title="Notifications" />

    <PageBanner title="Notifications" class="mb-2">
        <template #actions>
            <Button v-if="notifications.data.some((n) => !n.read_at)" variant="outline" @click="markAllRead">Mark all read</Button>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6 max-w-2xl px-4 pb-12">
        <Card>
            <CardContent class="p-0">
                <EmptyState v-if="props.notifications.data.length === 0" compact title="No notifications yet" description="" />
                <ul v-else class="divide-y">
                    <li
                        v-for="n in props.notifications.data"
                        :key="n.id"
                        :class="['cursor-pointer p-3 text-sm hover:bg-accent', !n.read_at ? 'bg-accent/40' : '']"
                        @click="openNotification(n)"
                    >
                        <p>{{ n.message }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ new Date(n.created_at).toLocaleString() }}</p>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <InertiaPagination v-if="props.notifications.data.length" :paginator="props.notifications" :only="['notifications']" class="mt-4" />
    </div>
</template>
