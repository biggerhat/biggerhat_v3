<script setup lang="ts">
import { insertNodesAtCaret, renderGameText, serializeGameText, textToNodes } from '@/lib/gameTextDom';
import { onMounted, ref, watch } from 'vue';

/**
 * A text field for card text where icons (suits, ranges, soulstones, …) appear as real icons while you edit, and the
 * IconTokenPalette inserts them at the caret. The value stays the same plain string with `{{tag}}` tokens everywhere
 * else in the app (GameText renders it); pasted `{{tag}}` text turns into icons, and Backspace deletes an icon whole.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string | null | undefined;
        placeholder?: string;
        /** Enter adds a line break; single-line fields ignore it. */
        multiline?: boolean;
        ariaLabel?: string;
        /** Approximate visible lines for a multiline field. */
        rows?: number;
    }>(),
    { placeholder: '', multiline: false, ariaLabel: undefined, rows: 2 },
);
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const el = ref<HTMLElement | null>(null);
const empty = ref(!props.modelValue);
// What the editor last emitted, so an echo of our own update never re-renders (and resets the caret).
let current = props.modelValue ?? '';

onMounted(() => {
    if (el.value) renderGameText(el.value, props.modelValue, props.multiline);
});

watch(
    () => props.modelValue,
    (value) => {
        const next = value ?? '';
        if (next === current || !el.value) return;
        current = next;
        empty.value = !next;
        renderGameText(el.value, next, props.multiline);
    },
);

const onInput = () => {
    if (!el.value) return;
    current = serializeGameText(el.value);
    empty.value = current === '';
    emit('update:modelValue', current);
};

const onKeydown = (e: KeyboardEvent) => {
    if (e.key !== 'Enter' || e.isComposing) return;
    e.preventDefault();
    if (props.multiline && el.value) insertNodesAtCaret(el.value, textToNodes('\n', true));
};

// Paste as plain text (turning any {{tag}} into icons) so no foreign markup or styling gets in.
const onPaste = (e: ClipboardEvent) => {
    e.preventDefault();
    const text = e.clipboardData?.getData('text/plain') ?? '';
    if (el.value && text) insertNodesAtCaret(el.value, textToNodes(text, props.multiline));
};
</script>

<template>
    <div
        ref="el"
        data-game-text-editor
        contenteditable="true"
        role="textbox"
        :aria-multiline="multiline"
        :aria-label="ariaLabel ?? placeholder"
        :data-placeholder="placeholder"
        :data-empty="empty"
        class="game-text-editor w-full whitespace-pre-wrap break-words rounded-md border border-input bg-transparent px-3 py-1.5 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        :style="{ minHeight: multiline ? `${rows * 1.25 + 0.75}rem` : '1.75rem' }"
        @input="onInput"
        @keydown="onKeydown"
        @paste="onPaste"
    />
</template>

<style scoped>
.game-text-editor[data-empty='true']::before {
    content: attr(data-placeholder);
    color: hsl(var(--muted-foreground));
    pointer-events: none;
}
</style>
