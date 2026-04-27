<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * SEO meta-tag bundle. Drop into any page to override the site-wide defaults
 * defined in app.blade.php. Inertia's <Head> dedupes by the `head-key` /
 * `inertia="..."` attribute — keeping the same names here as the blade
 * defaults guarantees clean overrides on client-side navigation.
 *
 * Usage:
 *   <SeoHead
 *       :title="character.display_name"
 *       :description="`${character.display_name}, ${character.station} from ${factionName}.`"
 *       :image="character.front_image"
 *   />
 */
const props = defineProps<{
    title: string;
    description?: string | null;
    /** Path or absolute URL — relative paths are resolved against window.origin. */
    image?: string | null;
    /** og:type — defaults to 'article' for entity pages. */
    type?: 'website' | 'article' | 'profile';
    /** Override the canonical URL; defaults to the current page URL. */
    canonical?: string | null;
}>();

const page = usePage();

const fullTitle = computed(() => {
    const base = (page.props as { name?: string }).name ?? 'BiggerHat';
    if (! props.title) return base;
    return props.title.includes(base) ? props.title : `${props.title} - ${base}`;
});

const absoluteUrl = (path: string | null | undefined): string | null => {
    if (! path) return null;
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    if (typeof window === 'undefined') return path;
    if (path.startsWith('/')) return `${window.location.origin}${path}`;
    // Disk-relative storage path (e.g. "characters/foo.png").
    return `${window.location.origin}/storage/${path}`;
};

const canonicalUrl = computed(() => {
    if (props.canonical) return props.canonical;
    if (typeof window === 'undefined') return undefined;
    // Strip query/hash so canonical points at the canonical URL only.
    return `${window.location.origin}${window.location.pathname}`;
});

const imageUrl = computed(() => absoluteUrl(props.image ?? null));
const ogType = computed(() => props.type ?? 'article');
</script>

<template>
    <Head>
        <title>{{ fullTitle }}</title>
        <meta v-if="description" name="description" head-key="description" :content="description" />
        <link v-if="canonicalUrl" rel="canonical" head-key="canonical" :href="canonicalUrl" />

        <meta property="og:type" head-key="og:type" :content="ogType" />
        <meta property="og:title" head-key="og:title" :content="fullTitle" />
        <meta v-if="description" property="og:description" head-key="og:description" :content="description" />
        <meta v-if="canonicalUrl" property="og:url" head-key="og:url" :content="canonicalUrl" />
        <meta v-if="imageUrl" property="og:image" head-key="og:image" :content="imageUrl" />

        <meta name="twitter:card" head-key="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" head-key="twitter:title" :content="fullTitle" />
        <meta v-if="description" name="twitter:description" head-key="twitter:description" :content="description" />
        <meta v-if="imageUrl" name="twitter:image" head-key="twitter:image" :content="imageUrl" />
    </Head>
</template>
