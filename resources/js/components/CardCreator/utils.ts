export const factionColorMap: Record<string, string> = {
    arcanists: '--arcanists',
    bayou: '--bayou',
    guild: '--guild',
    explorers_society: '--explorerssociety',
    neverborn: '--neverborn',
    outcasts: '--outcasts',
    resurrectionists: '--resurrectionists',
    ten_thunders: '--tenthunders',
};

export function getFactionVar(faction: string | null): string {
    return faction ? (factionColorMap[faction] ?? '--primary') : '--primary';
}

export function factionGradient(factionVar: string, secondFactionVar: string | null): string {
    if (secondFactionVar) {
        return `linear-gradient(to right, hsl(var(${factionVar})), hsl(var(${secondFactionVar})))`;
    }
    return `hsl(var(${factionVar}))`;
}

export function splitSuits(suits: string | null): string[] {
    return suits ? suits.split(/\s+/).filter(Boolean) : [];
}

// Formats an action range for display. Appends an inches mark when numeric,
// otherwise renders the raw value (e.g. *, X, Ml). Renders '-' when nullish.
export function formatRange(range: number | string | null | undefined): string {
    if (range === null || range === undefined || range === '') return '-';
    const str = String(range);
    return /^-?\d+(\.\d+)?$/.test(str) ? `${str}"` : str;
}

export function contentScaleClass(charCount: number): 'scale-sm' | 'scale-md' | 'scale-lg' | 'scale-xl' {
    if (charCount > 1500) return 'scale-sm';
    if (charCount > 1000) return 'scale-md';
    if (charCount > 600) return 'scale-lg';
    return 'scale-xl';
}

// Tarot proportions (550x950), tiered up as content grows — used by the
// headless-capture pages (CaptureCombinedCrewCard.vue, Capture.vue) to size
// their wrapper div so the card grows to fit content instead of the face
// component shrinking its own text into a fixed box. Each capture-only face
// picks its own width/height for `CombinedCrewCardFace`, but `CardFrontFace`/
// `CardBackFace` are also embedded inside a fixed-aspect responsive
// container elsewhere (CardRenderer.vue's live flip-preview), so for those
// two the sizing has to live in the capture page instead of the component.
const TAROT_RATIO = 950 / 550;
const TAROT_WIDTH_TIERS = [550, 650, 750, 850, 950, 1050, 1150];

export function tarotCardSize(totalChars: number): { width: number; height: number } {
    const tierIndex = Math.min(Math.floor(totalChars / 900), TAROT_WIDTH_TIERS.length - 1);
    const width = TAROT_WIDTH_TIERS[tierIndex];
    return { width, height: Math.round(width * TAROT_RATIO) };
}

let cachedFontCSS: string | null = null;

export async function fetchFontEmbedCSS(): Promise<string> {
    if (cachedFontCSS) return cachedFontCSS;
    const res = await fetch('/font/M4E-Symbols.otf');
    const buf = await res.arrayBuffer();
    const base64 = btoa(String.fromCharCode(...new Uint8Array(buf)));
    cachedFontCSS = `@font-face { font-family: 'M4E-Symbols'; src: url(data:font/opentype;base64,${base64}) format('opentype'); }`;
    return cachedFontCSS;
}

export function blobToDataURL(blobUrl: string): Promise<string> {
    return fetch(blobUrl)
        .then((res) => res.blob())
        .then(
            (blob) =>
                new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result as string);
                    reader.readAsDataURL(blob);
                }),
        );
}

export function createComboImage(frontDataUrl: string, backDataUrl: string): Promise<string> {
    return new Promise((resolve) => {
        const frontImg = new Image();
        const backImg = new Image();
        let loaded = 0;
        const onLoad = () => {
            loaded++;
            if (loaded < 2) return;
            const gap = 20;
            const canvas = document.createElement('canvas');
            canvas.width = frontImg.width + backImg.width + gap;
            canvas.height = Math.max(frontImg.height, backImg.height);
            const ctx = canvas.getContext('2d')!;
            ctx.drawImage(frontImg, 0, 0);
            ctx.drawImage(backImg, frontImg.width + gap, 0);
            resolve(canvas.toDataURL('image/png'));
        };
        frontImg.onload = onLoad;
        backImg.onload = onLoad;
        frontImg.src = frontDataUrl;
        backImg.src = backDataUrl;
    });
}

export function triggerDownload(dataUrl: string, filename: string): void {
    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
