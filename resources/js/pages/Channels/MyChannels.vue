<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/vue3';

interface Channel {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    image_url: string | null;
    transmissions_count: number;
}

defineProps<{
    channels: Channel[];
}>();
</script>

<template>
    <Head title="My Channels" />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="My Channels" class="mb-2" />

        <div class="container mx-auto px-4">
            <div v-if="channels.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card v-for="channel in channels" :key="channel.id" class="flex flex-col">
                    <CardHeader class="pb-2">
                        <div class="flex items-center gap-3">
                            <img
                                v-if="channel.image_url"
                                :src="channel.image_url"
                                :alt="channel.name"
                                class="h-12 w-12 shrink-0 rounded-lg object-cover"
                            />
                            <div>
                                <Link :href="route('channels.view', channel.slug)" class="text-lg font-bold hover:underline">
                                    {{ channel.name }}
                                </Link>
                                <div class="text-xs text-muted-foreground">
                                    {{ channel.transmissions_count }} {{ channel.transmissions_count === 1 ? 'transmission' : 'transmissions' }}
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col justify-between">
                        <p v-if="channel.description" class="mb-3 text-sm text-muted-foreground">{{ channel.description }}</p>
                        <div class="flex gap-2">
                            <Button size="sm" @click="router.get(route('channels.view', channel.slug))">View</Button>
                            <Button size="sm" variant="outline" @click="router.get(route('transmissions.create', channel.slug))">
                                Add Transmission
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <EmptyState v-else title="No channels" description="You are not assigned to any channels yet." />
        </div>
    </div>
</template>
