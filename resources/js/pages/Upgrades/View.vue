<script setup lang="ts">
import UpgradeCardView from '@/components/UpgradeCardView.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { useFactionColor } from '@/composables/useFactionColor';
import { isMobileDevice } from '@/composables/useMobileDevice';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface UpgradeData {
    id: number;
    name: string;
    slug: string;
    domain: string;
    domain_label: string;
    type: string | null;
    type_label: string | null;
    faction: string | null;
    faction_label: string | null;
    faction_color: string | null;
    faction_logo: string | null;
    limitations: string | null;
    limitations_label: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
    plentiful: number | null;
    masters: Array<{ display_name: string; slug: string; faction: string | null }>;
    keywords: Array<{ name: string; slug: string }>;
    characters: Array<{ display_name: string; slug: string; faction: string | null }>;
    actions: Array<{ name: string; slug: string }>;
    abilities: Array<{ name: string; slug: string }>;
    triggers: Array<{ name: string; slug: string }>;
    markers: Array<{ name: string; slug: string | null }>;
    tokens: Array<{ name: string; slug: string | null }>;
}

const props = defineProps<{
    upgrade: UpgradeData;
}>();

const factionColor = computed(() => (props.upgrade.faction ? useFactionColor(props.upgrade.faction) : null));

const backRoute = computed(() => (props.upgrade.domain === 'crew' ? route('upgrades.crew.index') : route('upgrades.character.index')));

const backLabel = computed(() => (props.upgrade.domain === 'crew' ? 'Back to Crew Upgrades' : 'Back to Character Upgrades'));

const hasRelatedContent = computed(
    () =>
        props.upgrade.characters.length > 0 ||
        props.upgrade.keywords.length > 0 ||
        props.upgrade.actions.length > 0 ||
        props.upgrade.abilities.length > 0 ||
        props.upgrade.triggers.length > 0 ||
        props.upgrade.markers.length > 0 ||
        props.upgrade.tokens.length > 0,
);
</script>

<template>
    <Head :title="upgrade.name" />

    <div class="relative h-full w-full">
        <!-- Faction gradient accent -->
        <div
            v-if="factionColor"
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: `radial-gradient(ellipse at top, hsl(var(--${factionColor})) 0%, transparent 70%)` }"
        />
        <div
            v-else
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto px-4 pb-16 pt-6">
            <!-- Back link -->
            <Link
                :href="backRoute"
                class="group mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                {{ backLabel }}
            </Link>

            <div class="animate-fade-in-up">
                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Card image -->
                    <div class="lg:col-span-2">
                        <div v-if="upgrade.combination_image && !isMobileDevice()" class="overflow-hidden rounded-xl shadow-lg">
                            <img :src="`/storage/${upgrade.combination_image}`" :alt="upgrade.name" class="w-full rounded-xl" />
                        </div>
                        <div v-else class="mx-auto max-w-sm">
                            <UpgradeCardView :upgrade="upgrade" :show-link="false" />
                        </div>
                    </div>

                    <!-- Info panel -->
                    <div class="space-y-4">
                        <Card>
                            <CardHeader class="pb-3">
                                <div class="flex items-center gap-2">
                                    <img v-if="upgrade.faction_logo" :src="upgrade.faction_logo" :alt="upgrade.faction_label ?? ''" class="h-8 w-8" />
                                    <CardTitle class="text-2xl">{{ upgrade.name }}</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary">{{ upgrade.domain_label }} Upgrade</Badge>
                                    <Badge v-if="upgrade.type_label" variant="secondary">{{ upgrade.type_label }}</Badge>
                                    <Badge v-if="upgrade.limitations_label" variant="outline">{{ upgrade.limitations_label }}</Badge>
                                    <Badge v-if="upgrade.plentiful" variant="outline">Plentiful ({{ upgrade.plentiful }})</Badge>
                                </div>

                                <div v-if="upgrade.faction_label" class="flex items-center gap-2 text-sm">
                                    <span class="text-muted-foreground">Faction:</span>
                                    <span class="font-medium">{{ upgrade.faction_label }}</span>
                                </div>

                                <!-- Masters -->
                                <div v-if="upgrade.masters.length">
                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Masters</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <Link
                                            v-for="master in upgrade.masters"
                                            :key="master.slug"
                                            :href="route('characters.view', { character: master.slug, miniature: 1, slug: 'view' })"
                                        >
                                            <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                                {{ master.display_name }}
                                            </Badge>
                                        </Link>
                                    </div>
                                </div>

                                <!-- Keywords -->
                                <div v-if="upgrade.keywords.length">
                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Keywords</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <Link v-for="keyword in upgrade.keywords" :key="keyword.slug" :href="route('keywords.view', keyword.slug)">
                                            <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                                {{ keyword.name }}
                                            </Badge>
                                        </Link>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Related content -->
                <div v-if="hasRelatedContent" class="mt-12">
                    <Separator label="Related Content" class="mb-8" />

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <!-- Characters -->
                        <div v-if="upgrade.characters.length">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Characters</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <Link
                                    v-for="character in upgrade.characters"
                                    :key="character.slug"
                                    :href="route('characters.view', { character: character.slug, miniature: 1, slug: 'view' })"
                                >
                                    <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                        {{ character.display_name }}
                                    </Badge>
                                </Link>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div v-if="upgrade.actions.length">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Actions</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="action in upgrade.actions" :key="action.slug" variant="secondary">
                                    {{ action.name }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Abilities -->
                        <div v-if="upgrade.abilities.length">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Abilities</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="ability in upgrade.abilities" :key="ability.slug" variant="secondary">
                                    {{ ability.name }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Triggers -->
                        <div v-if="upgrade.triggers.length">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Triggers</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="trigger in upgrade.triggers" :key="trigger.slug" variant="secondary">
                                    {{ trigger.name }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Markers -->
                        <div v-if="upgrade.markers.length">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Markers</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="marker in upgrade.markers" :key="marker.name" variant="secondary">
                                    {{ marker.name }}
                                </Badge>
                            </div>
                        </div>

                        <!-- Tokens -->
                        <div v-if="upgrade.tokens.length">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Tokens</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="token in upgrade.tokens" :key="token.name" variant="secondary">
                                    {{ token.name }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
