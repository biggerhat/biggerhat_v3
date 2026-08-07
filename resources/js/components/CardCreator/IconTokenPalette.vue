<script setup lang="ts">
import GameIcon from '@/components/GameIcon.vue';

interface TokenOption {
    /** Inserted as {{token}} — matches GameText.vue's tagToIconType vocabulary. */
    token: string;
    iconType: string;
    label: string;
}

// Canonical keys only — tagToIconType's aliases (e.g. "stone"/"soulstones"
// both map to the same "soulstone" icon) are deliberately not duplicated here.
const TOKENS: TokenOption[] = [
    { token: 'ram', iconType: 'ram', label: 'Ram' },
    { token: 'mask', iconType: 'mask', label: 'Mask' },
    { token: 'crow', iconType: 'crow', label: 'Crow' },
    { token: 'tome', iconType: 'tome', label: 'Tome' },
    { token: 'soulstone', iconType: 'soulstone', label: 'Soulstone' },
    { token: 'melee', iconType: 'melee', label: 'Melee' },
    { token: 'missile', iconType: 'missile', label: 'Missile' },
    { token: 'magic', iconType: 'magic', label: 'Magic' },
    { token: 'pulse', iconType: 'pulse', label: 'Pulse' },
    { token: 'positive', iconType: 'positive', label: 'Positive Twist' },
    { token: 'negative', iconType: 'negative', label: 'Negative Twist' },
    { token: 'fortitude', iconType: 'physical_defense', label: 'Fortitude (Physical Defense)' },
    { token: 'warding', iconType: 'magical_defense', label: 'Warding (Magical Defense)' },
    { token: 'unusual', iconType: 'unusual_defense', label: 'Unusual Defense' },
    { token: 'signatureaction', iconType: 'signature_action', label: 'Signature Action' },
];

// Inserts {{token}} at the cursor of whatever text field last had focus.
// @mousedown.prevent on each button below stops the browser's default
// focus-shift-on-click, so document.activeElement (and its selection range)
// is still the field the user was typing in when this handler runs.
const insert = (token: string) => {
    const el = document.activeElement;
    if (!(el instanceof HTMLTextAreaElement) && !(el instanceof HTMLInputElement)) return;

    const text = `{{${token}}}`;
    const start = el.selectionStart ?? el.value.length;
    const end = el.selectionEnd ?? el.value.length;
    el.value = el.value.slice(0, start) + text + el.value.slice(end);
    // Vue's v-model listens for the native 'input' event to pick up the change.
    el.dispatchEvent(new Event('input', { bubbles: true }));

    const cursor = start + text.length;
    el.focus();
    el.setSelectionRange(cursor, cursor);
};
</script>

<template>
    <div class="flex flex-wrap gap-1">
        <button
            v-for="opt in TOKENS"
            :key="opt.token"
            type="button"
            :title="`Insert {{${opt.token}}} — ${opt.label}`"
            class="flex size-6 items-center justify-center rounded border border-input bg-transparent text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            @mousedown.prevent
            @click="insert(opt.token)"
        >
            <GameIcon :type="opt.iconType" class-name="text-xs" />
        </button>
    </div>
</template>
