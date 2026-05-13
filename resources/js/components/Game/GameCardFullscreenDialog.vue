<script setup lang="ts">
// Game-tracker wrapper around the shared CardFullscreenDialog. Kept as a
// separate component for stable callers (Games/Show.vue, GameCrewMemberDrawer)
// while the underlying dialog evolves — same prop surface, plus an optional
// backSrc so the in-game zoom view gets the same flip-button affordance as
// the database pages.
import CardFullscreenDialog from '@/components/CardFullscreenDialog.vue';

withDefaults(
    defineProps<{
        open: boolean;
        src: string | null;
        backSrc?: string | null;
        title?: string | null;
    }>(),
    { backSrc: null, title: null },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();
</script>

<template>
    <CardFullscreenDialog :open="open" :src="src" :back-src="backSrc" :title="title" @update:open="emit('update:open', $event)" />
</template>
