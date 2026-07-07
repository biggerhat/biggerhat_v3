<script setup lang="ts">
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CARD_HOVER_QUIET } from '@/lib/cardHover';
import { Head, Link } from '@inertiajs/vue3';
import { Activity, BookOpen, Coins, Hammer, ScrollText, ShieldAlert, Sword, Trophy } from 'lucide-vue-next';

interface Feature {
    icon: typeof Activity;
    title: string;
    body: string;
}

// What ships in Campaign Mode. Kept short — the teaser sells the shape of
// the feature, not the rulebook depth. Copy pulled from the Index of the
// Untold marketing one-pager.
const features: Feature[] = [
    {
        icon: Hammer,
        title: 'Custom Leader Builder',
        body: 'Hand-craft your campaign Leader from one of five Archetypes (Lucky Upstart, Heavy Hitter, Schemer, Talented Individual, Generalist).',
    },
    {
        icon: Coins,
        title: 'Scrip Economy',
        body: 'Hire weekly, barter equipment after every game, and watch your Arsenal grow over 4–12 weeks of play.',
    },
    {
        icon: ScrollText,
        title: 'Aftermath Wizard',
        body: 'Six-phase post-game flow handles XP, Doctor visits, injuries, and equipment barter — your Arsenal Sheet updates as you go.',
    },
    {
        icon: ShieldAlert,
        title: 'Injury & Annihilation',
        body: 'Models accumulate injuries from real game deaths; three injuries and they’re gone. Leaders get a one-time miraculous recovery.',
    },
    {
        icon: Trophy,
        title: 'Competitive or Narrative',
        body: 'Toggle competitive scoring to declare a campaign winner at end, or run pure narrative — both modes work.',
    },
    {
        icon: Sword,
        title: 'Solo or Multiplayer',
        body: 'Track your own crew alone (log games manually) or run a multiplayer campaign with invitations, organizer powers, and shared Arsenal Sheets.',
    },
];
</script>

<template>
    <Head title="Campaign Mode — Coming Soon" />

    <PageBanner title="Campaign Mode">
        <template #subtitle>
            <div class="flex flex-wrap items-center gap-2 px-2">
                <Badge variant="outline" class="text-[10px] uppercase">Coming Soon</Badge>
                <span class="text-sm text-muted-foreground">
                    M4E <strong class="text-foreground">Index of the Untold</strong> — a persistent crew, narrative arc, and the full Aftermath flow,
                    coming to BiggerHat.
                </span>
            </div>
        </template>
    </PageBanner>

    <div class="container mx-auto max-w-5xl px-4 pb-16">
        <!-- Hero pitch -->
        <Card class="mt-6 overflow-hidden border-2 border-primary/30 bg-gradient-to-br from-primary/10 via-primary/5 to-transparent">
            <CardContent class="space-y-3 p-6 md:p-8">
                <h2 class="text-2xl font-bold leading-tight md:text-3xl">A Malifaux campaign you'll actually finish.</h2>
                <p class="text-sm leading-relaxed text-muted-foreground md:text-base">
                    Most campaign systems collapse after week three because the bookkeeping crushes the fun. BiggerHat handles the spreadsheet so you
                    can show up at the table with a Leader you built, models you hired, scrip you earned, and a story that picks up where last week
                    ended. The Aftermath wizard does the math; you make the choices.
                </p>
                <p class="text-sm leading-relaxed text-muted-foreground md:text-base">
                    Currently in private beta with a small group of playtesters. Watch this space — or
                    <Link :href="route('feedback.show')" class="font-medium text-primary underline-offset-2 hover:underline"
                        >drop a note via feedback</Link
                    >
                    if you want early access.
                </p>
            </CardContent>
        </Card>

        <!-- Feature grid -->
        <section class="mt-10">
            <div class="mb-4 flex items-center gap-2">
                <BookOpen class="h-5 w-5 text-muted-foreground" />
                <HeadingEyebrow as="h2" class="text-lg">What's in the box</HeadingEyebrow>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card v-for="f in features" :key="f.title" :class="CARD_HOVER_QUIET">
                    <CardContent class="space-y-2 p-5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-md bg-primary/10">
                            <component :is="f.icon" class="h-5 w-5 text-primary" />
                        </div>
                        <h3 class="text-base font-semibold leading-tight">{{ f.title }}</h3>
                        <p class="text-xs leading-relaxed text-muted-foreground">{{ f.body }}</p>
                    </CardContent>
                </Card>
            </div>
        </section>

        <!-- CTA strip -->
        <Card class="mt-10 border-dashed">
            <CardContent class="flex flex-col items-center gap-3 p-6 text-center md:p-8">
                <Activity class="h-6 w-6 text-primary" />
                <h3 class="text-lg font-semibold">Want in early?</h3>
                <p class="max-w-xl text-sm text-muted-foreground">
                    Send a quick note via the feedback form letting me know you'd like playtester access. I'm rolling out the permission individually
                    while the catalog data is being entered from the rulebook.
                </p>
                <Link :href="route('feedback.show')">
                    <Button>Request playtester access</Button>
                </Link>
            </CardContent>
        </Card>
    </div>
</template>
