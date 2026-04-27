<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PageBanner from '@/components/PageBanner.vue';
import Button from '@/components/ui/button/Button.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Users } from 'lucide-vue-next';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    color_slug: string | null;
}

interface CrewUnit {
    id: number;
    is_commander: boolean;
}

interface Crew {
    id: number;
    slug: string;
    name: string;
    allegiance: Allegiance;
    crew_units: CrewUnit[];
    updated_at: string;
}

defineProps<{
    crews: Crew[];
}>();
</script>

<template>
    <Head title="My Crews — TOS" />
    <div class="relative">
        <PageBanner title="My Crews" class="mb-2">
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Build and save TOS Companies
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto space-y-4 sm:px-4">
            <div class="flex justify-end">
                <Button as="a" :href="route('tos.crews.create')" size="sm" class="gap-1">
                    <Plus class="size-4" /> New crew
                </Button>
            </div>

            <div v-if="crews.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="c in crews"
                    :key="c.id"
                    :href="route('tos.crews.view', c.slug)"
                    class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    <Card class="h-full overflow-hidden transition hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
                        <div :class="['h-1 w-full', c.allegiance.color_slug ? `bg-${c.allegiance.color_slug}` : 'bg-primary/40']" />
                        <CardContent class="space-y-1 p-4">
                            <p class="text-sm font-semibold">{{ c.name }}</p>
                            <p class="text-[11px] text-muted-foreground">{{ c.allegiance.name }}</p>
                            <p class="text-[10px] text-muted-foreground">
                                {{ c.crew_units.length }} {{ c.crew_units.length === 1 ? 'unit' : 'units' }}
                                <span v-if="c.crew_units.some((u) => u.is_commander)"> · Commander set</span>
                            </p>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <EmptyState v-else :icon="Users" title="No crews yet" description="Create one to start drafting a TOS Company." />
        </div>
    </div>
</template>
