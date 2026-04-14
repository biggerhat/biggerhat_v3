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

export function contentScaleClass(charCount: number): 'scale-sm' | 'scale-md' | 'scale-lg' | 'scale-xl' {
    if (charCount > 1500) return 'scale-sm';
    if (charCount > 1000) return 'scale-md';
    if (charCount > 600) return 'scale-lg';
    return 'scale-xl';
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
