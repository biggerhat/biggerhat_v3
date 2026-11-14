<script setup lang="ts">
import type { PropType } from 'vue';
const props = defineProps({
    keyword: {
        type: [Object, Array] as PropType<any>,
        required: false,
        default() {
            return {};
        },
    },
});
</script>

<template>
    <div>
        <Link :href="route('keywords.view', { keyword: props.keyword.slug })" class="hover:underline">
            {{ keyword.name }}
        </Link>
    </div>
</template>
