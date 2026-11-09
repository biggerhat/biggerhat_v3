/**
 * The M4E-Symbols icon vocabulary shared by GameIcon (renders one), GameText (turns `{{tag}}` into icons) and the
 * Card Creator's GameTextEditor (shows the icons while editing, stores `{{tag}}`).
 */

export const GAME_ICONS: Record<string, { glyph: string; alt: string; color?: string }> = {
    // Suits
    crow: { glyph: 'c', alt: 'Crow', color: 'text-green-700 dark:text-green-400' },
    crows: { glyph: 'c', alt: 'Crow', color: 'text-green-700 dark:text-green-400' },
    mask: { glyph: 'm', alt: 'Mask', color: 'text-purple-700 dark:text-purple-400' },
    masks: { glyph: 'm', alt: 'Mask', color: 'text-purple-700 dark:text-purple-400' },
    ram: { glyph: 'r', alt: 'Ram', color: 'text-red-600 dark:text-red-400' },
    rams: { glyph: 'r', alt: 'Ram', color: 'text-red-600 dark:text-red-400' },
    tome: { glyph: 't', alt: 'Tome', color: 'text-blue-600 dark:text-blue-400' },
    tomes: { glyph: 't', alt: 'Tome', color: 'text-blue-600 dark:text-blue-400' },
    soulstone: { glyph: 's', alt: 'Soulstone' },
    soulstones: { glyph: 's', alt: 'Soulstone' },

    // Range Types
    melee: { glyph: 'y', alt: 'Melee' },
    missile: { glyph: 'z', alt: 'Missile' },
    magic: { glyph: 'q', alt: 'Magic' },
    pulse: { glyph: 'p', alt: 'Pulse' },

    // Modifiers
    positive: { glyph: '+', alt: 'Positive' },
    negative: { glyph: '-', alt: 'Negative' },

    // Defensive Types
    physical_defense: { glyph: 'u', alt: 'Physical Defense' },
    magical_defense: { glyph: 'x', alt: 'Magical Defense' },
    unusual_defense: { glyph: 'v', alt: 'Unusual Defense' },

    // Signature Action
    signature_action: { glyph: 'f', alt: 'Signature Action' },
};

// Tag names from `{{tag}}` to GAME_ICONS keys.
export const TAG_TO_ICON_TYPE: Record<string, string> = {
    crow: 'crow',
    crows: 'crow',
    mask: 'mask',
    masks: 'mask',
    ram: 'ram',
    rams: 'ram',
    tome: 'tome',
    tomes: 'tome',
    soulstone: 'soulstone',
    soulstones: 'soulstone',
    stone: 'soulstone',
    melee: 'melee',
    missile: 'missile',
    magic: 'magic',
    pulse: 'pulse',
    positive: 'positive',
    negative: 'negative',
    fortitude: 'physical_defense',
    warding: 'magical_defense',
    unusual: 'unusual_defense',
    unusualdefense: 'unusual_defense',
    signatureaction: 'signature_action',
    '+': 'positive',
    '-': 'negative',
};

/** Matches `{{tag}}` in game text (a word, or + / -). */
export const GAME_TEXT_TOKEN_PATTERN = /\{\{(\w+|[+-])\}\}/g;
