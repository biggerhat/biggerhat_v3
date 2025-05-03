<template>
    <div>
        <div
            @click="flip"
            :class="['card-container', flipped ? 'flipped' : '']"
            class="mx-1"
        >
            <div class="front">
                <img :src='"/storage/" + miniature.front_image' :alt="miniature.display_name" class="rounded-lg">
            </div>
            <div class="back">
                <img :src='"/storage/" + miniature.back_image' :alt="miniature.display_name" class="rounded-lg">
            </div>
        </div>
    </div>

</template>

<script setup lang="ts">
import { ref } from "vue";

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

<style>
.card-container {
    margin: 0;
    padding: 0;
    position: relative;
    box-sizing: border-box;

    .front,
    .back {
        box-sizing: border-box;
        height: 100%;
        width: 100%;
        display: block;
        position: absolute;
        backface-visibility: hidden;
        transform-style: preserve-3d;
        transition: -webkit-transform ease 500ms;
        transition: transform ease 500ms;
    }
    .front {
        transform: rotateY(0deg);
    }
    .back {
        transform: rotateY(-180deg);
    }

    &.flipped {
        .front {
            transform: rotateY(180deg);
        }
        .back {
            transform: rotateY(0deg);
        }
    }
}
</style>
