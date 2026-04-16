<script setup lang="ts">
import PageBanner from '@/components/PageBanner.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';
import { BookOpen, Bot, ExternalLink, MessageSquare, Search, Swords, Zap } from 'lucide-vue-next';

const inviteUrl = 'https://discord.com/api/oauth2/authorize?client_id=796518413534887986&permissions=2214906944&scope=bot';

interface CommandInfo {
    name: string;
    aliases?: string[];
    description: string;
    example?: string;
}

const categories: { title: string; icon: any; commands: CommandInfo[] }[] = [
    {
        title: 'Character & Model Lookup',
        icon: Search,
        commands: [
            {
                name: '/mini',
                aliases: ['!mini'],
                description: 'Search for a character by name. Supports version filters: --alt, --special, --nightmare, --rotten, --promo',
                example: '/mini Lady Justice',
            },
            { name: '/miniature', description: 'Search for a specific sculpt variant of a miniature', example: '/miniature Rasputina Alt' },
            {
                name: '/keyword',
                aliases: ['!keyword'],
                description: 'Look up a keyword and see all associated characters with station breakdown',
                example: '/keyword Redchapel',
            },
            {
                name: '/faction',
                description: 'Show faction overview with character count, miniature count, and keyword count',
                example: '/faction Arcanists',
            },
        ],
    },
    {
        title: 'Cards & Upgrades',
        icon: BookOpen,
        commands: [
            {
                name: '/upgrade',
                aliases: ['!upgrade'],
                description: 'Search for character upgrades with card images',
                example: '/upgrade Inhuman Reflexes',
            },
            {
                name: '/crew',
                aliases: ['!crew'],
                description: 'Search for crew-level upgrades and see associated characters',
                example: '/crew Seeker',
            },
            {
                name: '/action',
                description: 'Look up game actions with full stat lines, triggers, and descriptions. Filter by Attack or Tactical',
                example: '/action Obey',
            },
            { name: '/ability', description: 'Search for character abilities with descriptions and soulstone costs', example: '/ability Armor' },
            {
                name: '/trigger',
                description: 'Search for action triggers with descriptions and associated actions',
                example: '/trigger Critical Strike',
            },
        ],
    },
    {
        title: 'Encounter & Game Mechanics',
        icon: Swords,
        commands: [
            { name: '/scheme', aliases: ['!scheme'], description: 'Look up scheme cards with images', example: '/scheme Breakthrough' },
            { name: '/strategy', aliases: ['!strategy'], description: 'Look up strategy cards with images', example: '/strategy Turf War' },
            { name: '/marker', aliases: ['!marker'], description: 'Look up marker sizes and associated terrain traits', example: '/marker Scrap' },
            { name: '/token', aliases: ['!token'], description: 'Look up token information and descriptions', example: '/token Focus' },
            { name: '/terrain', description: 'Search for terrain traits and their effects', example: '/terrain Climbable' },
        ],
    },
    {
        title: 'Collections & Media',
        icon: MessageSquare,
        commands: [
            {
                name: '/package',
                aliases: ['!package'],
                description: 'Search for product boxes with contents, factions, and store links',
                example: '/package Rasputina Core Box',
            },
            {
                name: '/blueprint',
                description: 'Search for miniature assembly/sculpt guides with publication dates',
                example: '/blueprint Lady Justice',
            },
            { name: '/channel', description: 'Search for community media channels (YouTube, podcasts)', example: '/channel Third Floor Wars' },
            {
                name: '/transmission',
                description: 'Search for videos, podcasts, and articles from community creators',
                example: '/transmission Malifaux',
            },
        ],
    },
    {
        title: 'Utility',
        icon: Zap,
        commands: [
            { name: '/ping', description: 'Check bot latency and uptime' },
            { name: '/botstats', description: 'Show how many servers the bot is in' },
            { name: '/help', aliases: ['!help'], description: 'Display all available commands' },
        ],
    },
];
</script>

<template>
    <Head title="Hat Gamin - Discord Bot" />
    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <PageBanner title="Hat Gamin" class="mb-2">
            <template #logo>
                <div class="flex size-16 items-center justify-center md:size-20">
                    <Bot class="size-10 text-primary md:size-14" />
                </div>
            </template>
            <template #subtitle>
                <div class="my-auto px-2 py-0 text-xs text-muted-foreground md:py-2 md:text-sm md:text-foreground">
                    Malifaux Discord Bot powered by BiggerHat
                </div>
            </template>
        </PageBanner>

        <div class="container mx-auto pb-8 sm:px-4">
            <!-- Hero card -->
            <Card class="mb-6 overflow-hidden">
                <CardContent class="p-6">
                    <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left">
                        <div class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-primary/10">
                            <Bot class="size-8 text-primary" />
                        </div>
                        <div class="flex-1">
                            <h2 class="text-xl font-bold">Hat Gamin</h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Look up characters, upgrades, keywords, schemes, strategies, and more — directly from Discord. Powered by the
                                BiggerHat database with autocomplete search and card images.
                            </p>
                            <div class="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                                <Badge variant="secondary">22 Commands</Badge>
                                <Badge variant="secondary">Slash Commands</Badge>
                                <Badge variant="secondary">Autocomplete</Badge>
                                <Badge variant="secondary">Card Images</Badge>
                            </div>
                        </div>
                        <a :href="inviteUrl" target="_blank" rel="noopener noreferrer">
                            <Button size="lg" class="gap-2">
                                <ExternalLink class="size-4" />
                                Add to Server
                            </Button>
                        </a>
                    </div>
                </CardContent>
            </Card>

            <!-- Features -->
            <div class="mb-6 grid gap-3 sm:grid-cols-3">
                <Card>
                    <CardContent class="p-4 text-center">
                        <Search class="mx-auto mb-2 size-6 text-primary" />
                        <div class="font-semibold">Autocomplete Search</div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Start typing and get instant suggestions for characters, keywords, upgrades, and more
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 text-center">
                        <BookOpen class="mx-auto mb-2 size-6 text-primary" />
                        <div class="font-semibold">Card Images</div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            View character cards, scheme cards, strategy cards, and upgrade cards right in Discord
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="p-4 text-center">
                        <Zap class="mx-auto mb-2 size-6 text-primary" />
                        <div class="font-semibold">Fast & Cached</div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Results are cached for speed. Works with both slash commands and traditional ! prefix
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Command reference -->
            <h2 class="mb-4 text-lg font-bold">Command Reference</h2>
            <div class="space-y-4">
                <Card v-for="category in categories" :key="category.title">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wider text-muted-foreground">
                            <component :is="category.icon" class="size-4" />
                            {{ category.title }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="px-0 pb-2">
                        <div v-for="cmd in category.commands" :key="cmd.name" class="border-t px-4 py-3 sm:px-6">
                            <div class="flex flex-wrap items-center gap-2">
                                <code class="rounded bg-primary/10 px-2 py-0.5 text-sm font-bold text-primary">{{ cmd.name }}</code>
                                <Badge v-for="alias in cmd.aliases ?? []" :key="alias" variant="outline" class="font-mono text-[10px]">{{
                                    alias
                                }}</Badge>
                            </div>
                            <p class="mt-1 text-sm text-muted-foreground">{{ cmd.description }}</p>
                            <div v-if="cmd.example" class="mt-1.5">
                                <code class="text-xs text-muted-foreground/70">{{ cmd.example }}</code>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Bottom CTA -->
            <div class="mt-8 text-center">
                <a :href="inviteUrl" target="_blank" rel="noopener noreferrer">
                    <Button size="lg" class="gap-2">
                        <Bot class="size-5" />
                        Add Hat Gamin to Your Server
                    </Button>
                </a>
                <p class="mt-2 text-xs text-muted-foreground">Free to use. No premium tiers. Powered by BiggerHat.net</p>
            </div>
        </div>
    </div>
</template>
