<script setup lang="ts">
defineProps({
    text: {
        type: String,
        required: false,
        default() {
            return '';
        },
    },
});
</script>

<template>
    <div class="inline-flex w-full items-center justify-center">
        <hr class="my-4 h-px w-3/4 border-0 bg-primary" />
        <span class="absolute left-1/2 -translate-x-1/2 bg-background px-3">{{ text }}</span>
    </div>
</template>
