<script setup lang="ts">
import { ref } from "vue";
import { router } from '@inertiajs/vue3';
import Button from "@/components/ui/button/Button.vue";

const flipped = ref(false);
const flip = () => {
    flipped.value = !flipped.value;
}

const props = defineProps({
    upgrade: {
        type: [Object, Array],
        required: false,
        default() {
            return {};
        }
    }
});
</script>

<template>
    <div v-if="upgrade.back_image" class="w-full text-center">
        <div @click="flip" class="mx-1 w-auto h-auto">
            <img v-if="!flipped" :src='"/storage/" + upgrade.front_image' :alt="upgrade.name" class="rounded-lg w-full h-full" />
            <img v-else :src='"/storage/" + upgrade.back_image' :alt="upgrade.name" class="rounded-lg w-full h-full" />
        </div>
        <div class="mt-1">
            <Button @click="router.get(route('upgrades.view', {'upgrade': props.upgrade.slug}))" size="sm" variant="link">
                View Upgrade
            </Button>
        </div>
    </div>
    <div v-else class="w-full text-center">
        <div class="mx-1 w-auto h-auto">
            <img :src='"/storage/" + upgrade.front_image' :alt="upgrade.name" class="rounded-lg w-full h-full" />
        </div>
        <div class="mt-1">
            <Button @click="router.get(route('upgrades.view', {'upgrade': props.upgrade.slug}))" size="sm" variant="link">
                View Upgrade
            </Button>
        </div>
    </div>
</template>
