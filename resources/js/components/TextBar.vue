<script setup lang="ts">

const props = defineProps({
    text: {
        type: String,
        required: false,
        default() {
            return '';
        }
    }
});
</script>

<template>
    <div class="inline-flex items-center justify-center w-full">
        <hr class="w-3/4 h-px my-4 border-0 bg-primary">
        <span class="absolute px-3 -translate-x-1/2 left-1/2 bg-background">{{ text }}</span>
    </div>
</template>
