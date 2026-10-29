<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Site-wide fallback meta tags. app.blade.php server-renders these, but Inertia deletes any
 * server-rendered head tag the client doesn't re-emit, so pages without their own <SeoHead> lost
 * their description/canonical/share tags after hydration. Rendering the same keys from the layout
 * keeps them; a page's <SeoHead> (mounted later, same head-keys) still overrides them.
 * Keep the wording in sync with app.blade.php.
 */
const DESCRIPTION =
    'BiggerHat is the comprehensive Malifaux database — characters, upgrades, keywords, lore, build tools, tournament tracker, and more. The fastest way to find anything Malifaux.';
const SHORT_DESCRIPTION = 'The comprehensive Malifaux database — characters, upgrades, keywords, lore, and tools.';

const canonical = computed(() => (typeof window === 'undefined' ? undefined : `${window.location.origin}${window.location.pathname}`));
const image = computed(() => (typeof window === 'undefined' ? undefined : `${window.location.origin}/images/biggerhat-og.png`));
</script>

<template>
    <Head>
        <meta name="description" head-key="description" :content="DESCRIPTION" />
        <link v-if="canonical" rel="canonical" head-key="canonical" :href="canonical" />
        <meta property="og:type" head-key="og:type" content="website" />
        <meta property="og:site_name" head-key="og:site_name" content="BiggerHat" />
        <meta property="og:description" head-key="og:description" :content="SHORT_DESCRIPTION" />
        <meta v-if="canonical" property="og:url" head-key="og:url" :content="canonical" />
        <meta v-if="image" property="og:image" head-key="og:image" :content="image" />
        <meta name="twitter:card" head-key="twitter:card" content="summary_large_image" />
        <meta name="twitter:description" head-key="twitter:description" :content="SHORT_DESCRIPTION" />
        <meta v-if="image" name="twitter:image" head-key="twitter:image" :content="image" />
    </Head>
</template>
