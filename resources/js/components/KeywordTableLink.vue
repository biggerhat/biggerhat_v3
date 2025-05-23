<script setup lang="ts">
    const props = defineProps({
        keyword: {
            type: [Object, Array],
            required: false,
            default() {
                return {};
            }
        }
    });
</script>

<template>
    <div>
        <Link :href="route('keywords.view', { keyword: props.keyword.slug })" class="hover:underline">
        {{ keyword.name }}
        </Link>
    </div>
</template>
