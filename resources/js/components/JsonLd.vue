<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Renders a Schema.org JSON-LD `<script type="application/ld+json">` block
 * inside Inertia's <Head>. Use a unique `head-key` per page-type so
 * client-side navigation overwrites cleanly instead of stacking blocks.
 *
 * Pass any Schema.org data object — see https://schema.org for shapes.
 *
 * Usage:
 *   <JsonLd head-key="organization" :data="{ '@context': 'https://schema.org', '@type': 'Organization', ... }" />
 */
const props = defineProps<{
    data: Record<string, unknown>;
    headKey?: string;
}>();

/**
 * Written raw into a <script> element, so anything that could close it or start an HTML comment must be escaped:
 * a tournament, crew or user name containing a closing script tag would otherwise end the block and inject markup into the
 * server-rendered page. The \u escapes are still valid JSON, so crawlers read the same data.
 */
const json = computed(() => JSON.stringify(props.data).replace(/[<>&\u2028\u2029]/g, (c) => `\\u${c.charCodeAt(0).toString(16).padStart(4, '0')}`));
</script>

<template>
    <Head>
        <!--
            Vue's SFC template compiler refuses literal <script> tags in
            templates (they're parsed as component script blocks). Rendering
            via <component :is="'script'"> sidesteps that restriction while
            still emitting a real <script> into Inertia's <Head>.
        -->
        <!-- eslint-disable-next-line vue/no-v-html, vue/no-v-text-v-html-on-component -->
        <component :is="'script'" type="application/ld+json" :head-key="headKey ?? 'jsonld'" v-html="json" />
    </Head>
</template>
