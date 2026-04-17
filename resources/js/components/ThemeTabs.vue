<script setup lang="ts">
import { type Theme, useAppearance } from '@/composables/useAppearance';
import { Check, Palette } from 'lucide-vue-next';

interface Props {
    class?: string;
}

const { class: containerClass = '' } = defineProps<Props>();

const { theme, updateAccentTheme } = useAppearance();

// `value` matches the CSS slug form (what `data-theme` expects and what
// useAppearance stores in the cookie / localStorage). `hueClass` is a Tailwind
// bg-<faction> utility already defined in the theme config — we use it for
// the swatch dot so the picker colors match the rest of the app exactly.
const options: { value: Theme; label: string; hueClass: string | null }[] = [
    { value: 'default', label: 'Default', hueClass: null },
    { value: 'arcanists', label: 'Arcanists', hueClass: 'bg-arcanists' },
    { value: 'bayou', label: 'Bayou', hueClass: 'bg-bayou' },
    { value: 'explorerssociety', label: "Explorer's", hueClass: 'bg-explorerssociety' },
    { value: 'guild', label: 'Guild', hueClass: 'bg-guild' },
    { value: 'neverborn', label: 'Neverborn', hueClass: 'bg-neverborn' },
    { value: 'outcasts', label: 'Outcasts', hueClass: 'bg-outcasts' },
    { value: 'resurrectionists', label: 'Resurrectionists', hueClass: 'bg-resurrectionists' },
    { value: 'tenthunders', label: 'Ten Thunders', hueClass: 'bg-tenthunders' },
];
</script>

<template>
    <div :class="['grid grid-cols-2 gap-2 sm:grid-cols-3', containerClass]">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="theme === option.value"
            :class="[
                'group relative flex items-center gap-2 rounded-md border px-3 py-2 text-left text-sm transition-colors',
                theme === option.value
                    ? 'border-primary bg-accent text-foreground'
                    : 'border-border text-muted-foreground hover:border-border hover:bg-accent/40 hover:text-foreground',
            ]"
            @click="updateAccentTheme(option.value)"
        >
            <span
                v-if="option.hueClass"
                :class="[option.hueClass, 'inline-block size-4 shrink-0 rounded-full ring-1 ring-border']"
                aria-hidden="true"
            />
            <Palette v-else class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            <span class="truncate">{{ option.label }}</span>
            <Check v-if="theme === option.value" class="ml-auto size-3.5 shrink-0 text-primary" aria-hidden="true" />
        </button>
    </div>
</template>
