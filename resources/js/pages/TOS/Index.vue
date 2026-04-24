<script setup lang="ts">
import AllegianceLogo from '@/components/AllegianceLogo.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';
import { Swords, Users } from 'lucide-vue-next';

interface Allegiance {
    id: number;
    slug: string;
    name: string;
    short_name: string | null;
    type: string;
    is_syndicate: boolean;
    logo_path: string | null;
    color_slug: string | null;
}

defineProps<{
    allegiances: Allegiance[];
    syndicates: Allegiance[];
}>();
</script>

<template>
    <Head title="The Other Side" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="The Other Side" class="mb-2">
            <template #subtitle>
                <div class="my-auto flex items-center gap-2 px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Wyrd's mass-battle steampunk wargame — database browser
                    <Badge class="border-amber-500/60 bg-amber-500/10 px-1.5 py-0 text-[9px] font-bold text-amber-600 dark:text-amber-400">
                        Beta
                    </Badge>
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto sm:px-4">
            <div class="mb-8">
                <h2 class="mb-3 font-semibold">Allegiances</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="a in allegiances"
                        :key="a.id"
                        :href="route('tos.allegiances.view', a.slug)"
                        class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
                            <div :class="['h-1 w-full', a.color_slug ? `bg-${a.color_slug}` : 'bg-primary/40']" />
                            <CardContent class="flex items-center gap-3 p-4">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <AllegianceLogo :allegiance="a.slug" class-name="size-6" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ a.name }}</p>
                                    <p class="text-[11px] capitalize text-muted-foreground">{{ a.type }}</p>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>

            <div v-if="syndicates.length" class="mb-8">
                <h2 class="mb-3 font-semibold">Syndicates</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Link
                        v-for="s in syndicates"
                        :key="s.id"
                        :href="route('tos.allegiances.view', s.slug)"
                        class="block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Card class="h-full overflow-hidden transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-primary/30 hover:shadow-md">
                            <div :class="['h-1 w-full', s.color_slug ? `bg-${s.color_slug}` : 'bg-primary/40']" />
                            <CardContent class="flex items-center gap-3 p-4">
                                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <Users class="size-6" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold">{{ s.name }}</p>
                                    <p class="text-[11px] capitalize text-muted-foreground">{{ s.type }} syndicate</p>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
            </div>

            <div class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                <Swords class="mx-auto mb-2 size-8 opacity-50" />
                <p>Units, Assets, Stratagems, and Envoys land in upcoming releases.</p>
            </div>
        </div>
    </div>
</template>
