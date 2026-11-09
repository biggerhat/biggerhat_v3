<script setup lang="ts">
import { GAME_ICONS } from '@/lib/gameIcons';
import { computed, HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    type: string;
    className?: HTMLAttributes['class'];
    /** Font-size relative to the current text context, in em. The
     * M4E-Symbols glyphs are drawn with substantial internal padding, so a
     * literal 1em renders visibly smaller than surrounding text — confirmed
     * via screenshot at roughly half height at 1em, and still visibly
     * *larger* than surrounding text at 1.75em, so 1.3 splits the
     * difference. Applied via inline style rather than a Tailwind text-*
     * class so it always wins regardless of what a caller passes through
     * `className` (some older call sites still pass a now-superseded
     * text-sm/text-xs for sizing; inline style beats those unconditionally). */
    sizeEm?: number;
}

const props = withDefaults(defineProps<Props>(), { sizeEm: 1.3 });

const icon = computed(() => GAME_ICONS[props.type.toLowerCase()]);
</script>

<template>
    <span
        v-if="icon"
        :class="[className, icon.color]"
        :style="{ fontSize: sizeEm + 'em' }"
        class="game-icon font-['M4E-Symbols'] font-normal leading-none"
        role="img"
        :aria-label="icon.alt"
        >{{ icon.glyph }}</span
    >
</template>
