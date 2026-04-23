<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { AlertCircle, MessageSquareText } from 'lucide-vue-next';
import { computed } from 'vue';

interface GroupItem {
    label: string;
    href: string;
    count: number | null;
}

interface Group {
    title: string;
    description: string;
    items: GroupItem[];
}

interface TopPage {
    pageTitle: string;
    pagePath: string;
    screenPageViews: number;
}

interface ChartPoint {
    date: string;
    visitors: number;
    pageViews: number;
}

interface Analytics {
    summary: { visitors: number; pageViews: number } | null;
    topPages: TopPage[] | null;
    chart: ChartPoint[] | null;
    error: string | null;
}

const props = defineProps<{
    groups: Group[];
    stats: { pending_feedback: number | null };
    analytics: Analytics | null;
}>();

// Simple SVG sparkline for the 30-day visitors chart.
const chartPath = computed(() => {
    const points = props.analytics?.chart ?? [];
    if (points.length === 0) return '';
    const width = 600;
    const height = 80;
    const max = Math.max(...points.map((p) => p.visitors), 1);
    const step = width / Math.max(points.length - 1, 1);
    return points
        .map((p, i) => {
            const x = (i * step).toFixed(1);
            const y = (height - (p.visitors / max) * height).toFixed(1);
            return `${i === 0 ? 'M' : 'L'}${x},${y}`;
        })
        .join(' ');
});

const chartAreaPath = computed(() => {
    const path = chartPath.value;
    if (!path) return '';
    return `${path} L600,80 L0,80 Z`;
});

const chartMax = computed(() => {
    const points = props.analytics?.chart ?? [];
    return points.length ? Math.max(...points.map((p) => p.visitors), 0) : 0;
});
</script>

<template>
    <Head title="Admin Dashboard" />
    <div class="container mx-auto space-y-6 px-4 py-6 lg:px-8 xl:px-12">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Admin</h1>
                <p class="text-sm text-muted-foreground">Overview and shortcuts to admin sections.</p>
            </div>

            <!-- Inbox / quick stats -->
            <div v-if="stats.pending_feedback !== null" class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Pending Feedback</CardTitle>
                        <MessageSquareText class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <Link :href="route('admin.feedback.index', { status: 'new' })" class="block">
                            <div class="text-2xl font-bold">{{ stats.pending_feedback }}</div>
                            <p class="text-xs text-muted-foreground">Click to review new entries</p>
                        </Link>
                    </CardContent>
                </Card>
            </div>

            <!-- Group tiles -->
            <div class="grid gap-4 md:grid-cols-2">
                <Card v-for="group in groups" :key="group.title">
                    <CardHeader>
                        <CardTitle>{{ group.title }}</CardTitle>
                        <CardDescription>{{ group.description }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-1">
                            <li v-for="item in group.items" :key="item.label">
                                <Link :href="item.href" class="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-muted">
                                    <span>{{ item.label }}</span>
                                    <Badge v-if="item.count !== null" variant="secondary">{{ item.count }}</Badge>
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Analytics (super_admin only) -->
            <div v-if="analytics" class="space-y-4">
                <div>
                    <h2 class="text-xl font-semibold tracking-tight">Site analytics</h2>
                    <p class="text-sm text-muted-foreground">From Google Analytics. Cached for 24 hours.</p>
                </div>

                <div v-if="analytics.error" class="flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/5 p-3 text-sm">
                    <AlertCircle class="mt-0.5 h-4 w-4 text-amber-500" />
                    <div>
                        <p class="font-medium">Analytics unavailable</p>
                        <p class="text-muted-foreground">{{ analytics.error }}</p>
                    </div>
                </div>

                <template v-else>
                    <div class="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium">Visitors (last 7 days)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">{{ analytics.summary?.visitors ?? 0 }}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardTitle class="text-sm font-medium">Page views (last 7 days)</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="text-2xl font-bold">{{ analytics.summary?.pageViews ?? 0 }}</div>
                            </CardContent>
                        </Card>
                    </div>

                    <Card v-if="analytics.chart && analytics.chart.length > 0">
                        <CardHeader>
                            <CardTitle class="text-sm font-medium">Visitors — last 30 days</CardTitle>
                            <CardDescription>Peak: {{ chartMax }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <svg viewBox="0 0 600 80" class="h-20 w-full" preserveAspectRatio="none">
                                <path :d="chartAreaPath" fill="currentColor" class="text-primary/10" />
                                <path :d="chartPath" fill="none" stroke="currentColor" stroke-width="1.5" class="text-primary" />
                            </svg>
                        </CardContent>
                    </Card>

                    <Card v-if="analytics.topPages && analytics.topPages.length > 0">
                        <CardHeader>
                            <CardTitle class="text-sm font-medium">Top pages — last 7 days</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul class="space-y-1 text-sm">
                                <li v-for="page in analytics.topPages" :key="page.pagePath" class="flex items-center justify-between gap-4 py-1">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-medium">{{ page.pageTitle }}</div>
                                        <div class="truncate text-xs text-muted-foreground">{{ page.pagePath }}</div>
                                    </div>
                                    <Badge variant="secondary">{{ page.screenPageViews }}</Badge>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
</template>
