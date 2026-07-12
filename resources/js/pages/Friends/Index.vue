<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useConfirm } from '@/composables/useConfirm';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface UserMini {
    id: number;
    name: string;
}

defineProps<{
    friends: { friendship_id: number; user: UserMini }[];
    requests_received: { id: number; user: UserMini }[];
    requests_sent: { id: number; user: UserMini }[];
}>();

const confirmDialog = useConfirm();

// Search to add — mirrors the same pattern used for Campaign's "invite an
// existing player" picker (both hit the shared users.search endpoint).
const search = ref('');
const results = ref<UserMini[]>([]);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (q) => {
    if (searchTimer) clearTimeout(searchTimer);
    if (!q || q.trim().length < 2) {
        results.value = [];
        return;
    }
    searchTimer = setTimeout(async () => {
        const res = await fetch(route('users.search') + '?q=' + encodeURIComponent(q.trim()), {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            results.value = data.users ?? [];
        }
    }, 250);
});

const sendRequest = (userId: number) => {
    router.post(
        route('friends.store'),
        { user_id: userId },
        {
            onSuccess: () => {
                search.value = '';
                results.value = [];
            },
        },
    );
};

const acceptRequest = (id: number) => router.post(route('friends.accept', id));

const declineRequest = (id: number) => router.delete(route('friends.destroy', id));

const cancelRequest = (id: number) => router.delete(route('friends.destroy', id));

const unfriend = async (friendshipId: number, name: string) => {
    if (!(await confirmDialog({ title: 'Remove Friend', message: `Remove ${name} from your friends?`, destructive: true }))) return;
    router.delete(route('friends.destroy', friendshipId));
};
</script>

<template>
    <Head title="Friends" />

    <PageBanner title="Friends" class="mb-2">
        <template #subtitle>
            <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Add friends to invite them into campaigns and games faster.
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto mt-6 max-w-3xl space-y-6 px-4 pb-12">
        <Card>
            <CardHeader><CardTitle>Add a Friend</CardTitle></CardHeader>
            <CardContent>
                <div class="relative">
                    <Label>Search by name</Label>
                    <Input v-model="search" placeholder="Search players…" />
                    <ul v-if="results.length" class="absolute z-10 mt-1 w-full space-y-1 rounded-md border bg-popover p-1 shadow-md">
                        <li
                            v-for="u in results"
                            :key="u.id"
                            class="flex items-center justify-between rounded px-2 py-1.5 text-sm hover:bg-accent"
                        >
                            <span>{{ u.name }}</span>
                            <Button size="sm" @click="sendRequest(u.id)">Add</Button>
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>

        <Card v-if="requests_received.length">
            <CardHeader><CardTitle>Friend Requests</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <div v-for="r in requests_received" :key="r.id" class="flex items-center justify-between rounded-md border p-2 text-sm">
                    <span class="font-medium">{{ r.user.name }}</span>
                    <div class="flex gap-2">
                        <Button size="sm" @click="acceptRequest(r.id)">Accept</Button>
                        <Button size="sm" variant="ghost" @click="declineRequest(r.id)">Decline</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card v-if="requests_sent.length">
            <CardHeader><CardTitle>Sent Requests</CardTitle></CardHeader>
            <CardContent class="space-y-2">
                <div v-for="r in requests_sent" :key="r.id" class="flex items-center justify-between rounded-md border p-2 text-sm">
                    <span>{{ r.user.name }}</span>
                    <Button size="sm" variant="ghost" @click="cancelRequest(r.id)">Cancel</Button>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Your Friends</CardTitle></CardHeader>
            <CardContent>
                <EmptyState v-if="friends.length === 0" compact title="No friends yet" description="Search above to add your first one." />
                <div v-else class="space-y-2">
                    <div v-for="f in friends" :key="f.friendship_id" class="flex items-center justify-between rounded-md border p-2 text-sm">
                        <span class="font-medium">{{ f.user.name }}</span>
                        <Button size="sm" variant="ghost" @click="unfriend(f.friendship_id, f.user.name)">Remove</Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
