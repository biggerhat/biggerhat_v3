<script setup lang="ts">
import { ref } from "vue";
import { router } from '@inertiajs/vue3';
import Button from "@/components/ui/button/Button.vue";

const flipped = ref(false);
const flip = () => {
    flipped.value = !flipped.value;
}

const props = defineProps({
    miniature: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    }
});
</script>

<template>
    <div class="w-full text-center">
        <div @click="flip" class="mx-1 w-auto h-auto">
            <img v-if="!flipped" :src='"/storage/" + miniature.front_image' :alt="miniature.display_name" class="rounded-lg w-full h-full" />
            <img v-else :src='"/storage/" + miniature.back_image' :alt="miniature.display_name" class="rounded-lg w-full h-full" />
        </div>
        <div class="mt-1">
            <Button @click="router.get(route('characters.view', {'character': props.miniature.character.slug, 'miniature': props.miniature.id, 'slug': props.miniature.slug}))" size="sm" variant="link">
                View Character
            </Button>
        </div>
    </div>
</template>
