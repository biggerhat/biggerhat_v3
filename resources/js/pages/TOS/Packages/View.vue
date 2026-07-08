<script setup lang="ts">
import AddToWishlist from '@/components/AddToWishlist.vue';
import HeadingEyebrow from '@/components/HeadingEyebrow.vue';
import SeoHead from '@/components/SeoHead.vue';
import CardImage from '@/components/TOS/CardImage.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { CARD_HOVER_GROUP } from '@/lib/cardHover';
import { type SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, ExternalLink, Library, Package as PackageIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PackageData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sku: string | null;
    upc: string | null;
    msrp: number | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
    is_preassembled: boolean;
    released_at: string | null;
    units: Array<{
        name: string;
        slug: string;
        quantity: number;
        allegiances: Array<{ slug: string; name: string }>;
        first_sculpt_slug: string | null;
    }>;
    store_links: Array<{ store_name: string; url: string }>;
}

const props = defineProps<{
    package: PackageData;
}>();

const formattedMsrp = computed(() => {
    if (!props.package.msrp) return null;
    return `$${(props.package.msrp / 100).toFixed(2)}`;
});

const STORE_REF = 'hEWYov8ywW';
const withStoreRef = (raw: string): string => {
    try {
        const url = new URL(raw);
        if (!url.searchParams.has('bg_ref')) {
            url.searchParams.set('bg_ref', STORE_REF);
        }
        return url.toString();
    } catch {
        return raw;
    }
};

// ─── Collection ───
const page = usePage<SharedData>();
const isAuthenticated = computed(() => !!page.props.auth.user);
const collectionPackageIds = computed(() => page.props.auth.collection_package_ids ?? []);
const packageInCollection = computed(() => collectionPackageIds.value.includes(props.package.id));

const collectionProcessing = ref(false);
const addPackageToCollection = () => {
    const pkgIds = page.props.auth.collection_package_ids;
    const wasAbsent = !pkgIds.includes(props.package.id);
    if (wasAbsent) pkgIds.push(props.package.id);

    router.post(
        route('collection.add_package'),
        { package_id: props.package.id },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (collectionProcessing.value = true),
            onError: () => {
                if (wasAbsent) {
                    const idx = pkgIds.indexOf(props.package.id);
                    if (idx !== -1) pkgIds.splice(idx, 1);
                }
            },
            onFinish: () => (collectionProcessing.value = false),
        },
    );
};

const togglePackageCollection = () => {
    const pkgIds = page.props.auth.collection_package_ids;
    const wasInCollection = pkgIds.includes(props.package.id);
    if (wasInCollection) {
        const idx = pkgIds.indexOf(props.package.id);
        if (idx !== -1) pkgIds.splice(idx, 1);
    } else {
        pkgIds.push(props.package.id);
    }

    router.post(
        route('collection.toggle_package'),
        { package_id: props.package.id },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (collectionProcessing.value = true),
            onError: () => {
                if (wasInCollection) {
                    if (!pkgIds.includes(props.package.id)) pkgIds.push(props.package.id);
                } else {
                    const idx = pkgIds.indexOf(props.package.id);
                    if (idx !== -1) pkgIds.splice(idx, 1);
                }
            },
            onFinish: () => (collectionProcessing.value = false),
        },
    );
};
</script>

<template>
    <SeoHead
        :title="package.name"
        :description="package.description || (package.units.length ? `${package.units.length} units` : 'The Other Side package')"
        :image="package.front_image"
    />

    <div class="relative h-full w-full">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{ background: 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)' }"
        />

        <div class="container mx-auto pb-8 pt-4 sm:px-4 lg:pb-16 lg:pt-6">
            <Link
                :href="route('tos.packages.index')"
                class="group mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground lg:mb-6"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                Back to Packages
            </Link>

            <div class="animate-fade-in-up">
                <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
                    <!-- Image -->
                    <div class="order-2 lg:order-1 lg:col-span-2">
                        <div class="overflow-hidden rounded-xl shadow-lg">
                            <CardImage
                                :src="package.combination_image ?? package.front_image"
                                :alt="package.name"
                                :placeholder-icon="PackageIcon"
                                aspect-class="aspect-[4/3]"
                                rounded-class=""
                            />
                        </div>
                    </div>

                    <!-- Info panel -->
                    <div class="order-1 space-y-3 lg:order-2 lg:space-y-4">
                        <Card>
                            <CardHeader class="pb-3">
                                <CardTitle class="text-xl leading-tight lg:text-2xl">{{ package.name }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="flex flex-wrap gap-2">
                                    <Badge v-if="package.is_preassembled" variant="outline">Pre-assembled</Badge>
                                    <Badge v-if="formattedMsrp" variant="outline">{{ formattedMsrp }}</Badge>
                                </div>

                                <div class="space-y-1.5 text-sm">
                                    <div v-if="package.sku" class="flex items-center gap-2">
                                        <span class="text-muted-foreground">SKU:</span>
                                        <span class="font-medium">{{ package.sku }}</span>
                                    </div>
                                    <div v-if="package.upc" class="flex items-center gap-2">
                                        <span class="text-muted-foreground">UPC:</span>
                                        <span class="font-medium">{{ package.upc }}</span>
                                    </div>
                                    <div v-if="package.released_at" class="flex items-center gap-2">
                                        <span class="text-muted-foreground">Released:</span>
                                        <span class="font-medium">{{ package.released_at }}</span>
                                    </div>
                                </div>

                                <p v-if="package.description" class="text-sm leading-relaxed text-muted-foreground">
                                    {{ package.description }}
                                </p>

                                <div v-if="package.store_links.length">
                                    <HeadingEyebrow class="mb-1.5">Buy</HeadingEyebrow>
                                    <div class="flex flex-col gap-2">
                                        <a
                                            v-for="link in package.store_links"
                                            :key="link.url"
                                            :href="withStoreRef(link.url)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <Button variant="outline" class="w-full justify-between">
                                                {{ link.store_name }}
                                                <ExternalLink class="h-4 w-4" />
                                            </Button>
                                        </a>
                                    </div>
                                </div>

                                <div v-if="isAuthenticated" class="space-y-2">
                                    <HeadingEyebrow class="mb-1.5">Collection</HeadingEyebrow>
                                    <Button
                                        :variant="packageInCollection ? 'default' : 'outline'"
                                        class="w-full gap-2"
                                        :class="packageInCollection ? 'bg-green-600 hover:bg-green-700' : ''"
                                        :disabled="collectionProcessing"
                                        @click="packageInCollection ? togglePackageCollection() : addPackageToCollection()"
                                    >
                                        <Check v-if="packageInCollection" class="h-4 w-4" />
                                        <Library v-else class="h-4 w-4" />
                                        {{ packageInCollection ? 'In Collection' : 'Add to Collection' }}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Wishlist -->
                        <AddToWishlist type="package" :id="package.id" />
                    </div>
                </div>

                <!-- Units -->
                <div v-if="package.units.length" class="mt-8 lg:mt-12">
                    <Separator label="Units" class="mb-6" />
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                        <Link
                            v-for="unit in package.units"
                            :key="unit.slug"
                            :href="unit.first_sculpt_slug ? route('tos.units.view', unit.first_sculpt_slug) : route('tos.units.index')"
                            class="group"
                        >
                            <Card :class="['h-full', CARD_HOVER_GROUP]">
                                <CardContent class="flex items-center gap-2 p-3">
                                    <span class="text-sm font-medium group-hover:text-primary">{{ unit.name }}</span>
                                    <Badge v-if="unit.quantity > 1" variant="secondary" class="ml-auto text-xs"> x{{ unit.quantity }} </Badge>
                                </CardContent>
                            </Card>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
