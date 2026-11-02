import { ref } from 'vue';

/**
 * Open state of the Cmd-K palette (mounted once, in AppSidebarHeader). Shared so other entry points —
 * the mobile tab bar's Search tab — can open it without prop-drilling or DOM events.
 */
export const commandPaletteOpen = ref(false);

export function openCommandPalette() {
    commandPaletteOpen.value = true;
}
