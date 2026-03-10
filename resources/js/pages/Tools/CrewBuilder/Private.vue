<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ArrowLeft, Lock } from 'lucide-vue-next';
</script>

<template>
    <Head title="Private Crew — Crew Builder" />

    <div class="relative pb-12">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />
        <PageBanner title="Crew Builder" />

        <div class="container mx-auto mt-6 px-4 lg:px-6">
            <div class="mb-4">
                <Button variant="outline" size="sm" class="gap-1.5" as="a" :href="route('tools.crew_builder.index')">
                    <ArrowLeft class="size-4" />
                    <span class="hidden sm:inline">Build a Crew</span>
                </Button>
            </div>

            <div class="mx-auto max-w-md">
                <Card>
                    <CardContent class="flex flex-col items-center gap-3 p-8 text-center">
                        <div class="flex size-12 items-center justify-center rounded-full bg-muted">
                            <Lock class="size-6 text-muted-foreground" />
                        </div>
                        <h2 class="text-lg font-semibold">This crew list is private</h2>
                        <p class="text-sm text-muted-foreground">The owner has made this crew list private. It is no longer available for viewing.</p>
                        <Button as="a" :href="route('tools.crew_builder.index')" class="mt-2"> Build Your Own Crew </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
