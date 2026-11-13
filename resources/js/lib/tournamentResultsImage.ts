/**
 * Draws a shareable results card (1200×630, the usual link-preview size) for a finished tournament with the browser's
 * own canvas — no image service, nothing leaves the page. Pure drawing: it takes plain data and resolves with a PNG blob.
 */
export interface ResultsImagePlace {
    rank: number;
    name: string;
    /** Team members, faction or allegiance. */
    sub: string | null;
    score: string;
}

export interface ResultsImageData {
    title: string;
    subtitle: string;
    places: ResultsImagePlace[];
    /** Side awards as ready-made lines, e.g. "Best painted: Mia". Drawn small along the bottom; as many as fit. */
    awards?: string[];
    footer: string;
}

const W = 1200;
const H = 630;
const MEDALS: Record<number, { fill: string; text: string; label: string }> = {
    1: { fill: '#f5c84b', text: '#3b2a00', label: '1st' },
    2: { fill: '#c9ced6', text: '#262a31', label: '2nd' },
    3: { fill: '#c98a52', text: '#35200b', label: '3rd' },
};
const FONT = `-apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif`;

/** Shorten text with an ellipsis until it fits `max` pixels at the context's current font. */
const fit = (ctx: CanvasRenderingContext2D, text: string, max: number): string => {
    if (ctx.measureText(text).width <= max) return text;
    let t = text;
    while (t.length > 1 && ctx.measureText(`${t}…`).width > max) t = t.slice(0, -1);
    return `${t.trimEnd()}…`;
};

const roundRect = (ctx: CanvasRenderingContext2D, x: number, y: number, w: number, h: number, r: number) => {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
};

export function drawResultsImage(canvas: HTMLCanvasElement, data: ResultsImageData): void {
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Canvas is not available in this browser.');

    const bg = ctx.createLinearGradient(0, 0, W, H);
    bg.addColorStop(0, '#12151b');
    bg.addColorStop(1, '#1b1f29');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = '#f5c84b';
    ctx.fillRect(0, 0, W, 8);

    ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = '#ffffff';
    ctx.font = `700 56px ${FONT}`;
    ctx.fillText(fit(ctx, data.title, W - 120), 60, 108);
    ctx.fillStyle = '#9aa3b2';
    ctx.font = `400 28px ${FONT}`;
    ctx.fillText(fit(ctx, data.subtitle, W - 120), 60, 154);

    // Podium order: 2nd, 1st, 3rd, bars rising towards the winner.
    const byRank = [...data.places].slice(0, 3);
    const order = byRank.length === 3 ? [byRank[1], byRank[0], byRank[2]] : byRank;
    const cardW = 330;
    const gap = 30;
    const totalW = order.length * cardW + (order.length - 1) * gap;
    const startX = (W - totalW) / 2;
    const baseY = 560;

    order.forEach((place, i) => {
        const medal = MEDALS[Math.min(place.rank, 3)];
        const heights: Record<number, number> = { 1: 300, 2: 250, 3: 220 };
        const h = heights[Math.min(place.rank, 3)];
        const x = startX + i * (cardW + gap);
        const y = baseY - h;

        roundRect(ctx, x, y, cardW, h, 18);
        ctx.fillStyle = '#242936';
        ctx.fill();
        roundRect(ctx, x, y, cardW, 12, 6);
        ctx.fillStyle = medal.fill;
        ctx.fill();

        ctx.fillStyle = medal.fill;
        ctx.font = `700 34px ${FONT}`;
        ctx.textAlign = 'center';
        ctx.fillText(medal.label, x + cardW / 2, y + 62);

        ctx.fillStyle = '#ffffff';
        ctx.font = `700 38px ${FONT}`;
        ctx.fillText(fit(ctx, place.name, cardW - 40), x + cardW / 2, y + 120);

        if (place.sub) {
            ctx.fillStyle = '#9aa3b2';
            ctx.font = `400 22px ${FONT}`;
            ctx.fillText(fit(ctx, place.sub, cardW - 40), x + cardW / 2, y + 156);
        }

        ctx.fillStyle = '#d6dbe6';
        ctx.font = `600 26px ${FONT}`;
        ctx.fillText(place.score, x + cardW / 2, y + h - 28);
        ctx.textAlign = 'left';
    });

    if (data.awards?.length) {
        ctx.fillStyle = '#d6b45c';
        ctx.font = `500 22px ${FONT}`;
        ctx.fillText(fit(ctx, `Awards · ${data.awards.join(' · ')}`, 700), 60, H - 22);
    }

    ctx.fillStyle = '#6f7889';
    ctx.font = `500 22px ${FONT}`;
    ctx.textAlign = 'right';
    ctx.fillText(data.footer, W - 60, H - 22);
    ctx.textAlign = 'left';
}

export function resultsImageBlob(data: ResultsImageData): Promise<Blob> {
    const canvas = document.createElement('canvas');
    drawResultsImage(canvas, data);

    return new Promise((resolve, reject) =>
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not create the image.'))), 'image/png'),
    );
}
