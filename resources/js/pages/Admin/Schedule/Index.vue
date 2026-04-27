<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';

interface Task {
    description: string;
    command: string;
    expression: string;
    timezone: string;
    next_run: string;
}

defineProps<{ tasks: Task[] }>();

const formatDate = (s: string) => new Date(s).toLocaleString(undefined, { dateStyle: 'short', timeStyle: 'short' });
</script>

<template>
    <Head title="Scheduled Tasks - Admin" />
    <div class="container mx-auto space-y-4 px-4 py-6 lg:px-8 xl:px-12">
        <div class="flex items-center gap-2">
            <Clock class="size-5" />
            <h1 class="text-2xl font-semibold tracking-tight">Scheduled Tasks</h1>
            <Badge variant="secondary">{{ tasks.length }}</Badge>
        </div>
        <p class="text-sm text-muted-foreground">
            Every entry registered through Laravel's scheduler. Cron must run <code class="rounded bg-muted px-1 text-xs">php artisan schedule:run</code>
            every minute for these to fire.
        </p>

        <div class="space-y-2">
            <Card v-for="(task, idx) in tasks" :key="idx">
                <CardContent class="space-y-1 p-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold">{{ task.description }}</span>
                        <Badge variant="outline" class="font-mono text-[10px]">{{ task.expression }}</Badge>
                        <Badge variant="secondary" class="text-[10px]">{{ task.timezone }}</Badge>
                    </div>
                    <code class="block truncate font-mono text-[11px] text-muted-foreground">{{ task.command }}</code>
                    <div class="text-xs text-muted-foreground">Next run: {{ formatDate(task.next_run) }}</div>
                </CardContent>
            </Card>
            <div v-if="!tasks.length" class="py-12 text-center text-sm text-muted-foreground">No scheduled tasks registered.</div>
        </div>
    </div>
</template>
