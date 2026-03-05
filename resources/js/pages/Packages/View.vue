<script setup lang="ts">
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { isMobileDevice } from '@/composables/useMobileDevice';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';

interface PackageData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    factions: Array<{ value: string; label: string; color: string; logo: string }>;
    sku: string | null;
    upc: string | null;
    msrp: number | null;
    distributor_description: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
    sculpt_version: string;
    sculpt_version_label: string;
    is_preassembled: boolean;
    released_at: string | null;
    characters: Array<{ display_name: string; slug: string; faction: string; faction_color: string }>;
    miniatures: Array<{ display_name: string; slug: string }>;
    keywords: Array<{ name: string; slug: string }>;
}

const props = defineProps<{
    package: PackageData;
}>();

const primaryFactionColor = computed(() => {
    if (props.package.factions.length > 0) {
        return props.package.factions[0].color;
    }
    return null;
});

const hasRelatedContent = computed(
    () => props.package.characters.length > 0 || props.package.miniatures.length > 0 || props.package.keywords.length > 0,
);

const formattedMsrp = computed(() => {
    if (!props.package.msrp) return null;
    return `$${(props.package.msrp / 100).toFixed(2)}`;
});
</script>

<template>
    <Head :title="package.name" />

    <div class="relative h-full w-full">
        <div
            v-if="primaryFactionColor"
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: `radial-gradient(ellipse at top, hsl(var(--${primaryFactionColor})) 0%, transparent 70%)` }"
        />
        <div
            v-else
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto px-4 pb-16 pt-6">
            <Link
                :href="route('packages.index')"
                class="group mb-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                Back to Packages
            </Link>

            <div class="animate-fade-in-up">
                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Package image -->
                    <div class="lg:col-span-2">
                        <div v-if="package.combination_image && !isMobileDevice()" class="overflow-hidden rounded-xl shadow-lg">
                            <img :src="`/storage/${package.combination_image}`" :alt="package.name" class="w-full rounded-xl" />
                        </div>
                        <div v-else-if="package.front_image" class="overflow-hidden rounded-xl shadow-lg">
                            <img :src="`/storage/${package.front_image}`" :alt="package.name" class="w-full rounded-xl" />
                        </div>
                        <div v-else class="flex h-64 items-center justify-center rounded-xl border bg-muted">
                            <span class="text-muted-foreground">No image available</span>
                        </div>
                    </div>

                    <!-- Info panel -->
                    <div class="space-y-4">
                        <Card>
                            <CardHeader class="pb-3">
                                <div class="flex items-center gap-2">
                                    <FactionLogo
                                        v-if="package.factions.length"
                                        :faction="package.factions[0].value"
                                        class-name="h-8 w-8"
                                    />
                                    <CardTitle class="text-2xl">{{ package.name }}</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary">{{ package.sculpt_version_label }}</Badge>
                                    <Badge v-if="package.is_preassembled" variant="outline">Pre-assembled</Badge>
                                    <Badge v-if="formattedMsrp" variant="outline">{{ formattedMsrp }}</Badge>
                                </div>

                                <!-- Factions -->
                                <div v-if="package.factions.length">
                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Factions</div>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge v-for="faction in package.factions" :key="faction.value" variant="outline" class="gap-1.5">
                                            <FactionLogo :faction="faction.value" class-name="h-4 w-4" />
                                            {{ faction.label }}
                                        </Badge>
                                    </div>
                                </div>

                                <div v-if="package.sku" class="flex items-center gap-2 text-sm">
                                    <span class="text-muted-foreground">SKU:</span>
                                    <span class="font-medium">{{ package.sku }}</span>
                                </div>

                                <div v-if="package.released_at" class="flex items-center gap-2 text-sm">
                                    <span class="text-muted-foreground">Released:</span>
                                    <span class="font-medium">{{ package.released_at }}</span>
                                </div>

                                <div v-if="package.description" class="text-sm text-muted-foreground">
                                    {{ package.description }}
                                </div>

                                <!-- Keywords -->
                                <div v-if="package.keywords.length">
                                    <div class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Keywords</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <Link v-for="keyword in package.keywords" :key="keyword.slug" :href="route('keywords.view', keyword.slug)">
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
                        <div v-if="package.characters.length">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Characters</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <Link
                                    v-for="character in package.characters"
                                    :key="character.slug"
                                    :href="route('characters.view', { character: character.slug, miniature: 1, slug: 'view' })"
                                >
                                    <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                        {{ character.display_name }}
                                    </Badge>
                                </Link>
                            </div>
                        </div>

                        <!-- Miniatures -->
                        <div v-if="package.miniatures.length">
                            <h4 class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Miniatures</h4>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-for="miniature in package.miniatures" :key="miniature.slug" variant="secondary">
                                    {{ miniature.display_name }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
