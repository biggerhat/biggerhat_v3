<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Shield } from 'lucide-vue-next';
import { computed, ref, type HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    /** Allegiance slug (e.g. "kings_empire", "court_of_two"). */
    allegiance: string;
    className?: HTMLAttributes['class'];
}

const props = defineProps<Props>();
const page = usePage<SharedData>();

/**
 * Resolve via the `tos_allegiance_info` shared prop populated in
 * HandleInertiaRequests. Falls back to a Shield icon when:
 *   - the slug isn't in the canonical AllegianceEnum (admin-created entry),
 *   - logo_path is empty (assets not yet uploaded), or
 *   - the <img> 404s at runtime (the @error handler flips imgFailed to true).
 */
const info = computed(() => page.props.tos_allegiance_info?.[props.allegiance] ?? null);
const src = computed(() => info.value?.logo ?? '');
const alt = computed(() => info.value?.name ?? props.allegiance);
const imgFailed = ref(false);
const hasLogo = computed(() => Boolean(src.value) && !imgFailed.value);
</script>

<template>
    <img
        v-if="hasLogo"
        :src="src"
        :class="className"
        :alt="alt"
        loading="lazy"
        decoding="async"
        @error="imgFailed = true"
    />
    <Shield v-else :class="className" aria-hidden="true" />
</template>
