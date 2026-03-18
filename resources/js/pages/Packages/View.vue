<script setup lang="ts">
import AddToWishlist from '@/components/AddToWishlist.vue';
import FactionLogo from '@/components/FactionLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { imageLabel, imageSrc } from '@/composables/useBlueprintImages';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, ExternalLink, FileImage, Library, Package } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface PackageData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    factions: Array<{ value: string; label: string; color: string; logo: string }>;
    sku: string | null;
    upc: string | null;
    msrp: number | null;
    category: string | null;
    category_label: string | null;
    front_image: string | null;
    back_image: string | null;
    combination_image: string | null;
    sculpt_version: string;
    sculpt_version_label: string;
    is_preassembled: boolean;
    released_at: string | null;
    characters: Array<{
        display_name: string;
        slug: string;
        faction: string;
        faction_color: string;
        quantity: number;
        standard_miniature: { id: number; slug: string } | null;
    }>;
    miniatures: Array<{ display_name: string; slug: string }>;
    keywords: Array<{ name: string; slug: string }>;
    store_links: Array<{ store_name: string; url: string }>;
}

const props = defineProps<{
    package: PackageData;
}>();

const primaryFactionColor = computed(() => {
    return props.package.factions.length > 0 ? props.package.factions[0].color : null;
});

const formattedMsrp = computed(() => {
    if (!props.package.msrp) return null;
    return `$${(props.package.msrp / 100).toFixed(2)}`;
});

const images = computed(() => {
    const imgs: Array<{ src: string; label: string }> = [];
    if (props.package.front_image) {
        imgs.push({ src: `/storage/${props.package.front_image}`, label: 'Front' });
    }
    if (props.package.back_image) {
        imgs.push({ src: `/storage/${props.package.back_image}`, label: 'Back' });
    }
    return imgs;
});

const activeImage = ref(0);

// ─── Collection ───
const page = usePage<SharedData>();
const isAuthenticated = computed(() => !!page.props.auth.user);
const collectionPackageIds = computed(() => page.props.auth.collection_package_ids ?? []);
const packageInCollection = computed(() => collectionPackageIds.value.includes(props.package.id));

const csrfToken = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const collectionProcessing = ref(false);
const addPackageToCollection = async () => {
    collectionProcessing.value = true;
    const pkgIds = page.props.auth.collection_package_ids;
    if (!pkgIds.includes(props.package.id)) pkgIds.push(props.package.id);

    // Also optimistically add character miniatures
    const miniIds = page.props.auth.collection_miniature_ids;
    for (const c of props.package.characters ?? []) {
        if (c.standard_miniature && !miniIds.includes(c.standard_miniature.id)) {
            miniIds.push(c.standard_miniature.id);
        }
    }

    try {
        await fetch(route('collection.add_package'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ package_id: props.package.id }),
        });
    } finally {
        collectionProcessing.value = false;
    }
};

const togglePackageCollection = async () => {
    collectionProcessing.value = true;
    const pkgIds = page.props.auth.collection_package_ids;
    const idx = pkgIds.indexOf(props.package.id);
    if (idx !== -1) {
        pkgIds.splice(idx, 1);
    } else {
        pkgIds.push(props.package.id);
    }

    try {
        await fetch(route('collection.toggle_package'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ package_id: props.package.id }),
        });
    } finally {
        collectionProcessing.value = false;
    }
};
</script>

<template>
    <Head :title="package.name" />

    <div class="relative h-full w-full">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-64 opacity-[0.07] dark:opacity-[0.12]"
            :style="{
                background: primaryFactionColor
                    ? `radial-gradient(ellipse at top, hsl(var(--${primaryFactionColor})) 0%, transparent 70%)`
                    : 'radial-gradient(ellipse at top, hsl(var(--primary)) 0%, transparent 70%)',
            }"
        />

        <div class="container mx-auto pb-8 pt-4 sm:px-4 lg:pb-16 lg:pt-6">
            <Link
                :href="route('packages.index')"
                class="group mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground lg:mb-6"
            >
                <ArrowLeft class="h-4 w-4 transition-transform group-hover:-translate-x-1" />
                Back to Packages
            </Link>

            <div class="animate-fade-in-up">
                <div class="grid gap-6 lg:grid-cols-3 lg:gap-8">
                    <!-- Images -->
                    <div class="order-2 lg:order-1 lg:col-span-2">
                        <div v-if="images.length > 0" class="space-y-3">
                            <!-- Main image -->
                            <div class="overflow-hidden rounded-xl shadow-lg">
                                <img
                                    :src="images[activeImage].src"
                                    :alt="`${package.name} - ${images[activeImage].label}`"
                                    loading="lazy"
                                    decoding="async"
                                    class="w-full"
                                />
                            </div>
                            <!-- Thumbnails when both images exist -->
                            <div v-if="images.length > 1" class="flex gap-3">
                                <button
                                    v-for="(img, index) in images"
                                    :key="img.label"
                                    @click="activeImage = index"
                                    class="overflow-hidden rounded-lg border-2 transition-all"
                                    :class="activeImage === index ? 'border-primary shadow-md' : 'border-transparent opacity-60 hover:opacity-100'"
                                >
                                    <img
                                        :src="img.src"
                                        :alt="img.label"
                                        loading="lazy"
                                        decoding="async"
                                        class="h-24 w-20 object-cover sm:h-32 sm:w-24"
                                    />
                                </button>
                            </div>
                        </div>
                        <div v-else class="flex h-64 items-center justify-center rounded-xl border bg-muted">
                            <div class="text-center text-muted-foreground">
                                <Package class="mx-auto mb-2 h-10 w-10 opacity-40" />
                                <span>No image available</span>
                            </div>
                        </div>
                    </div>

                    <!-- Info panel -->
                    <div class="order-1 space-y-3 lg:order-2 lg:space-y-4">
                        <Card>
                            <CardHeader class="pb-3">
                                <div class="flex items-start gap-2.5">
                                    <FactionLogo
                                        v-if="package.factions.length"
                                        :faction="package.factions[0].value"
                                        class-name="h-8 w-8 shrink-0 mt-0.5"
                                    />
                                    <div>
                                        <CardTitle class="text-xl leading-tight lg:text-2xl">{{ package.name }}</CardTitle>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Badges row -->
                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary">{{ package.sculpt_version_label }}</Badge>
                                    <Badge v-if="package.category_label" variant="outline">{{ package.category_label }}</Badge>
                                    <Badge v-if="package.is_preassembled" variant="outline">Pre-assembled</Badge>
                                    <Badge v-if="formattedMsrp" variant="outline">{{ formattedMsrp }}</Badge>
                                </div>

                                <!-- Factions -->
                                <div v-if="package.factions.length > 1">
                                    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Factions</div>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge v-for="faction in package.factions" :key="faction.value" variant="outline" class="gap-1.5">
                                            <FactionLogo :faction="faction.value" class-name="h-4 w-4" />
                                            {{ faction.label }}
                                        </Badge>
                                    </div>
                                </div>

                                <!-- Details -->
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

                                <!-- Description -->
                                <p v-if="package.description" class="text-sm leading-relaxed text-muted-foreground">
                                    {{ package.description }}
                                </p>

                                <!-- Keywords -->
                                <div v-if="package.keywords.length">
                                    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Keywords</div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <Link v-for="keyword in package.keywords" :key="keyword.slug" :href="route('keywords.view', keyword.slug)">
                                            <Badge variant="outline" class="cursor-pointer transition-colors hover:bg-accent">
                                                {{ keyword.name }}
                                            </Badge>
                                        </Link>
                                    </div>
                                </div>

                                <!-- Store links -->
                                <div v-if="package.store_links.length">
                                    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Buy</div>
                                    <div class="flex flex-col gap-2">
                                        <a
                                            v-for="link in package.store_links"
                                            :key="link.url"
                                            :href="link.url"
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

                                <!-- Collection -->
                                <div v-if="isAuthenticated" class="space-y-2">
                                    <div class="mb-1.5 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Collection</div>
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

                <!-- Characters -->
                <div v-if="package.characters.length" class="mt-8 lg:mt-12">
                    <Separator label="Characters" class="mb-6" />
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                        <Link
                            v-for="character in package.characters"
                            :key="character.slug"
                            :href="
                                route('characters.view', {
                                    character: character.slug,
                                    miniature: character.standard_miniature?.id ?? 1,
                                    slug: character.standard_miniature?.slug ?? 'view',
                                })
                            "
                            class="group"
                        >
                            <Card class="h-full transition-all duration-200 group-hover:-translate-y-0.5 group-hover:shadow-lg">
                                <CardContent class="flex items-center gap-2 p-3">
                                    <FactionLogo :faction="character.faction" class-name="h-5 w-5 shrink-0" />
                                    <span class="text-sm font-medium group-hover:text-primary">{{ character.display_name }}</span>
                                    <Badge v-if="character.quantity > 1" variant="secondary" class="ml-auto text-xs">
                                        x{{ character.quantity }}
                                    </Badge>
                                </CardContent>
                            </Card>
                        </Link>
                    </div>
                </div>

                <!-- Miniatures -->
                <div v-if="package.miniatures.length" class="mt-8 lg:mt-12">
                    <Separator label="Miniatures" class="mb-6" />
                    <div class="flex flex-wrap gap-2">
                        <Badge v-for="miniature in package.miniatures" :key="miniature.slug" variant="secondary">
                            {{ miniature.display_name }}
                        </Badge>
                    </div>
                </div>

                <!-- Build Instructions -->
                <div v-if="package.blueprints?.length" class="mt-8 lg:mt-12">
                    <Separator label="Build Instructions" class="mb-6" />
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
                        <Dialog v-for="bp in package.blueprints" :key="bp.id">
                            <DialogTrigger as-child>
                                <button class="group cursor-pointer text-left">
                                    <Card
                                        class="h-full overflow-hidden transition-all duration-200 group-hover:-translate-y-0.5 group-hover:shadow-lg"
                                    >
                                        <div v-if="bp.image_path" class="border-b bg-muted/30">
                                            <img
                                                :src="imageSrc(bp.image_path)"
                                                :alt="bp.name"
                                                loading="lazy"
                                                decoding="async"
                                                class="h-32 w-full object-contain"
                                            />
                                        </div>
                                        <CardContent class="flex items-center gap-2 p-3">
                                            <FileImage class="h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span class="text-sm font-medium group-hover:text-primary">{{ bp.name }}</span>
                                        </CardContent>
                                    </Card>
                                </button>
                            </DialogTrigger>
                            <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
                                <DialogTitle class="text-lg font-semibold">{{ bp.name }}</DialogTitle>
                                <DialogDescription class="text-sm text-muted-foreground">
                                    {{ bp.image_path ? imageLabel(bp.image_path) : 'Assembly diagram' }}
                                </DialogDescription>
                                <img v-if="bp.image_path" :src="imageSrc(bp.image_path)" :alt="bp.name" class="mt-2 w-full rounded-lg border" />
                            </DialogContent>
                        </Dialog>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
