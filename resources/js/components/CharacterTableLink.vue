<script setup lang="ts">
const props = defineProps({
    character: {
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
        <Link :href="route('characters.view', { character: props.character.slug, miniature:props.character.standard_miniatures[0].id, slug: props.character.standard_miniatures[0].slug })" class="hover:underline">
            {{ character.display_name }}
        </Link>
    </div>
</template>
