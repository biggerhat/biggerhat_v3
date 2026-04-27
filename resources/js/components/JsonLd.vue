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

const json = computed(() => JSON.stringify(props.data));
</script>

<template>
    <Head>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <script type="application/ld+json" :head-key="headKey ?? 'jsonld'" v-html="json" />
    </Head>
</template>
