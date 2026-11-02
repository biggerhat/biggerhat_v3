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

// Alternating lighter/darker box background for a list of Action/Ability/
// Trigger boxes on the same faction tint — same color, two opacity steps.
export function alternatingBoxBg(factionVar: string, index: number): string {
    return `hsl(var(${factionVar}) / ${index % 2 === 0 ? 0.08 : 0.04})`;
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

// Tarot proportions (550x950), tiered up as content grows — used by the
// headless-capture pages (CaptureCombinedCrewCard.vue, Capture.vue) to size
// their wrapper div so the card grows to fit content instead of the face
// component shrinking its own text into a fixed box. Each capture-only face
// picks its own width/height for `CombinedCrewCardFace`, but `CardFrontFace`/
// `CardBackFace` are also embedded inside a fixed-aspect responsive
// container elsewhere (CardRenderer.vue's live flip-preview), so for those
// two the sizing has to live in the capture page instead of the component.
export const TAROT_RATIO = 950 / 550;
export const TAROT_WIDTH_TIERS = [550, 650, 750, 850, 950, 1050, 1150];

export function tarotCardSize(totalChars: number): { width: number; height: number } {
    const tierIndex = Math.min(Math.floor(totalChars / 900), TAROT_WIDTH_TIERS.length - 1);
    const width = TAROT_WIDTH_TIERS[tierIndex];
    return { width, height: Math.round(width * TAROT_RATIO) };
}

let cachedFontCSS: string | null = null;

const bytesToBase64 = (bytes: Uint8Array): string => {
    // Chunked so a large font never blows the argument limit of String.fromCharCode(...bytes).
    let binary = '';
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
        binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
    }
    return btoa(binary);
};

export async function fetchFontEmbedCSS(): Promise<string> {
    if (cachedFontCSS) return cachedFontCSS;
    const res = await fetch('/font/M4E-Symbols.otf');
    if (!res.ok) throw new Error(`Could not load the card symbol font (${res.status}).`);
    const base64 = bytesToBase64(new Uint8Array(await res.arrayBuffer()));
    // Only cache a successful load so a transient failure doesn't poison later exports.
    cachedFontCSS = `@font-face { font-family: 'M4E-Symbols'; src: url(data:font/opentype;base64,${base64}) format('opentype'); }`;
    return cachedFontCSS;
}

/**
 * Captures both card faces as PNG data URLs. Any `blob:` image (an uploaded portrait that hasn't been saved
 * yet) is inlined as a data URL for the capture and always restored afterwards — even when the capture throws.
 */
export async function captureCardFaces(
    frontEl: HTMLElement | null | undefined,
    backEl: HTMLElement | null | undefined,
): Promise<{ frontData: string; backData: string; frontEl: HTMLElement; backEl: HTMLElement } | null> {
    if (!frontEl || !backEl) return null;
    const { toPng } = await import('html-to-image');
    const fontEmbedCSS = await fetchFontEmbedCSS();
    const opts = { pixelRatio: 2, skipFonts: true, fontEmbedCSS };

    const swapped: { el: HTMLImageElement; src: string }[] = [];
    try {
        for (const img of [
            ...frontEl.querySelectorAll<HTMLImageElement>('img[src^="blob:"]'),
            ...backEl.querySelectorAll<HTMLImageElement>('img[src^="blob:"]'),
        ]) {
            swapped.push({ el: img, src: img.src });
            img.src = await blobToDataURL(img.src);
        }
        const frontData = await toPng(frontEl, opts);
        const backData = await toPng(backEl, opts);
        return { frontData, backData, frontEl, backEl };
    } finally {
        for (const { el, src } of swapped) el.src = src;
    }
}

export interface SaveCardResult {
    ok: boolean;
    errors: Record<string, any>;
    data: Record<string, any>;
    /** Set for anything that isn't a plain validation failure (network down, 419 expired session, 5xx). */
    message: string | null;
}

/** JSON save used by both card editors. Never throws: network and server failures come back as `message`. */
export async function saveCardJson(url: string, method: 'POST' | 'PUT', body: unknown, csrf: string): Promise<SaveCardResult> {
    try {
        const res = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok) return { ok: true, errors: {}, data, message: null };
        if (res.status === 422) return { ok: false, errors: data.errors ?? {}, data, message: null };
        const message =
            res.status === 419
                ? 'Your session expired. Reload the page and try again — copy your work first if you need to.'
                : (data.message ?? `Could not save (error ${res.status}).`);
        return { ok: false, errors: data.errors ?? {}, data, message };
    } catch {
        return { ok: false, errors: {}, data: {}, message: 'Could not reach the server. Check your connection and try again.' };
    }
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

// Real-world tarot card size (pg matches BonanzaDeck.blade.php's print CSS,
// which already produces correctly card-sized physical pages) — 2.75in x
// 4.75in, exactly the 550:950 ratio every tarotCardSize() tier shares.
const CARD_WIDTH_PT = 2.75 * 72;
const CARD_HEIGHT_PT = 4.75 * 72;

// Fits an element's captured pixel dimensions into the fixed physical card
// page, preserving aspect ratio (letterboxed, centered, never stretched).
// Every normal tarotCardSize() tier already shares the page's own 2.75:4.75
// ratio exactly, so this fills the page edge-to-edge with no letterboxing in
// the common case — it only kicks in for the rare pathologically-dense-card
// fallback (useTarotGrowToFit growing height past the tarot proportion).
function fitToCardPage(elWidth: number, elHeight: number): { w: number; h: number; x: number; y: number } {
    const scale = Math.min(CARD_WIDTH_PT / elWidth, CARD_HEIGHT_PT / elHeight);
    const w = elWidth * scale;
    const h = elHeight * scale;
    return { w, h, x: (CARD_WIDTH_PT - w) / 2, y: (CARD_HEIGHT_PT - h) / 2 };
}

// Two-page print-ready PDF (front, then back) from the same toPng() data
// URLs createComboImage() combines into a single downloadable PNG. The PAGE
// is always the fixed physical tarot card size (2.75in x 4.75in) regardless
// of how many pixels the capture used — capturing at a bigger tarotCardSize
// tier (for a content-dense card) means more pixels/crisper text scaled down
// to fit that same physical page, not a physically bigger page. Previously
// this sized the page 1:1 with the captured element's pixel dimensions
// (assuming 96dpi), which grew the PHYSICAL page every time a denser card
// needed a bigger capture tier, instead of keeping a fixed card-sized page.
export async function exportComboAsPdf(
    frontDataUrl: string,
    backDataUrl: string,
    frontEl: HTMLElement,
    backEl: HTMLElement,
    filename: string,
): Promise<void> {
    const { jsPDF } = await import('jspdf');

    const front = fitToCardPage(frontEl.offsetWidth, frontEl.offsetHeight);
    const back = fitToCardPage(backEl.offsetWidth, backEl.offsetHeight);

    const pdf = new jsPDF({
        orientation: 'portrait',
        unit: 'pt',
        format: [CARD_WIDTH_PT, CARD_HEIGHT_PT],
    });
    pdf.addImage(frontDataUrl, 'PNG', front.x, front.y, front.w, front.h);

    pdf.addPage([CARD_WIDTH_PT, CARD_HEIGHT_PT], 'portrait');
    pdf.addImage(backDataUrl, 'PNG', back.x, back.y, back.w, back.h);

    pdf.save(filename);
}

export function triggerDownload(dataUrl: string, filename: string): void {
    const link = document.createElement('a');
    link.href = dataUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
