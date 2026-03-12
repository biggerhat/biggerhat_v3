<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Head, Link, router } from '@inertiajs/vue3';
import { Globe, Heart, Lock, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface Wishlist {
    id: number;
    name: string;
    share_code: string;
    is_public: boolean;
    items_count: number;
    created_at: string;
}

defineProps<{
    wishlists: Wishlist[];
}>();

const newName = ref('');
const showCreate = ref(false);

const deleteTarget = ref<Wishlist | null>(null);
const deleting = ref(false);

function createWishlist() {
    if (!newName.value.trim()) return;
    router.post(route('wishlists.store'), { name: newName.value.trim() });
    newName.value = '';
    showCreate.value = false;
}

function confirmDelete() {
    if (!deleteTarget.value) return;
    deleting.value = true;
    router.delete(route('wishlists.destroy', deleteTarget.value.id), {
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="My Wishlists" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="My Wishlists">
            <template #subtitle>
                <div class="px-2 text-sm text-muted-foreground">Track packages, characters, and miniatures you want to add to your collection.</div>
            </template>
        </PageBanner>

        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <!-- Create new -->
            <div class="mb-6">
                <div v-if="showCreate" class="flex items-center gap-2">
                    <Input
                        v-model="newName"
                        placeholder="Wishlist name..."
                        class="max-w-sm"
                        autofocus
                        @keydown.enter="createWishlist"
                        @keydown.escape="showCreate = false"
                    />
                    <Button @click="createWishlist" :disabled="!newName.trim()">Create</Button>
                    <Button variant="ghost" @click="showCreate = false">Cancel</Button>
                </div>
                <Button v-else @click="showCreate = true">
                    <Plus class="mr-2 size-4" />
                    New Wishlist
                </Button>
            </div>

            <EmptyState
                v-if="wishlists.length === 0"
                title="No wishlists yet"
                description="Create a wishlist to start tracking items you want."
            />

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="wishlist in wishlists"
                    :key="wishlist.id"
                    class="transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg"
                >
                    <CardContent class="p-4">
                        <div class="flex items-start justify-between gap-3">
                            <Link :href="route('wishlists.show', wishlist.id)" class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <Heart class="size-4 shrink-0 text-primary" />
                                    <span class="truncate font-semibold transition-colors hover:text-primary">{{ wishlist.name }}</span>
                                </div>
                                <div class="mt-1.5 flex items-center gap-3 text-xs text-muted-foreground">
                                    <span>{{ wishlist.items_count }} {{ wishlist.items_count === 1 ? 'item' : 'items' }}</span>
                                    <span class="flex items-center gap-1">
                                        <Globe v-if="wishlist.is_public" class="size-3" />
                                        <Lock v-else class="size-3" />
                                        {{ wishlist.is_public ? 'Public' : 'Private' }}
                                    </span>
                                </div>
                            </Link>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-7 shrink-0 text-destructive hover:text-destructive"
                                @click="deleteTarget = wishlist"
                            >
                                <Trash2 class="size-3.5" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <!-- Delete confirmation dialog -->
    <Dialog :open="!!deleteTarget" @update:open="(v: boolean) => { if (!v) deleteTarget = null }">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete Wishlist</DialogTitle>
                <DialogDescription>
                    Are you sure you want to delete <span class="font-semibold">"{{ deleteTarget?.name }}"</span>? This will remove all items and cannot be
                    undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-3 sm:gap-x-3">
                <Button variant="outline" @click="deleteTarget = null" :disabled="deleting">Cancel</Button>
                <Button variant="destructive" @click="confirmDelete" :disabled="deleting">
                    {{ deleting ? 'Deleting...' : 'Delete' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
