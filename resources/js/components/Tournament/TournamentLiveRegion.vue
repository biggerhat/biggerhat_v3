<script setup lang="ts">
/** A visually hidden polite live region: screen readers announce `message` whenever it changes. */
defineProps<{ message: string }>();
</script>

<template>
    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-testid="live-region" data-no-print>{{ message }}</div>
</template>
