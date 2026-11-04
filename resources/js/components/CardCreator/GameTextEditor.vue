<script setup lang="ts">
import { insertNodesAtCaret, renderGameText, serializeGameText, textToNodes } from '@/lib/gameTextDom';
import { onMounted, ref, watch } from 'vue';

/**
 * A text field for card text where icons (suits, ranges, soulstones, …) appear as real icons while you edit, and the
 * IconTokenPalette inserts them at the caret. The value stays the same plain string with `{{tag}}` tokens everywhere
 * else in the app (GameText renders it); pasted `{{tag}}` text turns into icons, and Backspace deletes an icon whole.
 * Undo / redo (Ctrl/Cmd+Z, Ctrl+Shift+Z, Ctrl+Y) run on the editor's own history of values, because the browser's undo
 * stack loses track of icons inserted from code.
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
        // An outside change (draft restore, clone, import) is a fresh starting point for undo.
        history.length = 0;
        history.push(next);
        position = 0;
        renderGameText(el.value, next, props.multiline);
    },
);

// Undo history: whole values. Plain typing within a short pause shares one step; icon inserts and pastes are their own.
const MAX_STEPS = 100;
const TYPING_PAUSE_MS = 700;
const history: string[] = [props.modelValue ?? ''];
let position = 0;
let lastTypedAt = 0;

const record = (value: string, typing: boolean) => {
    if (value === history[position]) return;
    history.length = position + 1; // a new edit drops any redo steps
    const now = Date.now();
    if (typing && position > 0 && now - lastTypedAt < TYPING_PAUSE_MS) {
        history[position] = value;
    } else {
        history.push(value);
        if (history.length > MAX_STEPS) history.shift();
        position = history.length - 1;
    }
    lastTypedAt = typing ? now : 0;
};

const onInput = (e: Event) => {
    if (!el.value) return;
    current = serializeGameText(el.value);
    empty.value = current === '';
    const type = (e as InputEvent).inputType ?? '';
    record(current, type === 'insertText' || type === 'deleteContentBackward' || type === 'deleteContentForward');
    emit('update:modelValue', current);
};

const step = (direction: -1 | 1) => {
    const next = position + direction;
    if (!el.value || next < 0 || next >= history.length) return;
    position = next;
    lastTypedAt = 0;
    current = history[position];
    empty.value = current === '';
    renderGameText(el.value, current, props.multiline);
    // Put the caret at the end of the restored text so typing can continue.
    const range = document.createRange();
    range.selectNodeContents(el.value);
    range.collapse(false);
    const selection = window.getSelection();
    selection?.removeAllRanges();
    selection?.addRange(range);
    emit('update:modelValue', current);
};

const onBeforeInput = (e: InputEvent) => {
    if (e.inputType === 'historyUndo') {
        e.preventDefault();
        step(-1);
    } else if (e.inputType === 'historyRedo') {
        e.preventDefault();
        step(1);
    }
};

const onKeydown = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.isComposing) {
        const key = e.key.toLowerCase();
        if (key === 'z') {
            e.preventDefault();
            step(e.shiftKey ? 1 : -1);
            return;
        }
        if (key === 'y') {
            e.preventDefault();
            step(1);
            return;
        }
    }
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
        @beforeinput="onBeforeInput"
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
