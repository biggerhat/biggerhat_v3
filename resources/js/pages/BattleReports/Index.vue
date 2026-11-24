<script setup lang="ts">
import GameCard, { type ReportGame } from '@/components/BattleReports/GameCard.vue';
import EmptyState from '@/components/EmptyState.vue';
import HelpButton from '@/components/Help/HelpButton.vue';
import InertiaPagination from '@/components/InertiaPagination.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { PenLine } from 'lucide-vue-next';
import { computed } from 'vue';

/** Battle reports people wrote: the public ones for everyone, and (signed in) your own, drafts included. */
interface Row {
    uuid: string;
    title: string;
    excerpt: string;
    author: { name: string; slug: string } | null;
    date: string | null;
    game: ReportGame | null;
    status?: string;
    status_label?: string;
}
defineProps<{
    reports: {
        data: Row[];
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
    };
    mine: Row[];
}>();

const signedIn = computed(() => !!usePage<SharedData>().props.auth.user);
</script>

<template>
    <Head title="Battle reports" />
    <PageBanner title="Battle reports">
        <template #subtitle>
            <div class="my-auto flex flex-wrap items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                Games written up by the people who played them.
                <HelpButton topic="game-tracker" section="reports" />
            </div>
        </template>
        <template #actions>
            <div class="px-2 pb-2 sm:pb-0">
                <Button v-if="signedIn" as-child size="sm" class="max-sm:min-h-11" data-testid="report-new">
                    <Link :href="route('battle-reports.create')"><PenLine class="mr-1.5 size-4" /> Write a report</Link>
                </Button>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-4xl space-y-8 px-4 pb-8 sm:px-6">
        <section v-if="mine.length" aria-labelledby="mine-h" data-testid="my-reports">
            <h2 id="mine-h" class="mb-2 text-lg font-semibold">Yours</h2>
            <ul class="space-y-2">
                <li v-for="r in mine" :key="r.uuid">
                    <Card>
                        <CardContent class="flex items-center justify-between gap-3 p-3">
                            <div class="min-w-0">
                                <Link :href="route('battle-reports.show', r.uuid)" class="block truncate font-medium hover:underline">{{
                                    r.title
                                }}</Link>
                                <p class="text-xs text-muted-foreground">
                                    <Badge
                                        :variant="r.status === 'approved' ? 'default' : 'outline'"
                                        class="mr-1 px-1 py-0 text-2xs"
                                        data-testid="my-report-status"
                                        >{{ r.status_label }}</Badge
                                    >{{ r.date }}
                                </p>
                            </div>
                            <Button as-child size="sm" variant="outline" class="shrink-0 max-sm:min-h-11">
                                <Link :href="route('battle-reports.edit', r.uuid)">Edit</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </li>
            </ul>
        </section>

        <section aria-labelledby="public-h" data-testid="public-reports">
            <h2 id="public-h" class="mb-2 text-lg font-semibold">From the community</h2>
            <ul v-if="reports.data.length" class="space-y-3">
                <li v-for="r in reports.data" :key="r.uuid">
                    <Card>
                        <CardContent class="space-y-2 p-4">
                            <Link :href="route('battle-reports.show', r.uuid)" class="text-lg font-semibold hover:underline">{{ r.title }}</Link>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="r.author"
                                    >by <Link :href="route('stats.show', r.author.slug)" class="underline">{{ r.author.name }}</Link> · </template
                                >{{ r.date }}
                            </p>
                            <p v-if="r.excerpt" class="text-sm text-muted-foreground">{{ r.excerpt }}</p>
                            <GameCard v-if="r.game" :game="r.game" compact />
                        </CardContent>
                    </Card>
                </li>
            </ul>
            <EmptyState v-else title="No battle reports yet" description="Be the first: write up a game you played." />
            <InertiaPagination :paginator="reports" :only="['reports']" />
        </section>
    </div>
</template>
