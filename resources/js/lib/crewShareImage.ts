/**
 * Draws a shareable PNG "poster" of a crew list on a plain <canvas> — no extra dependencies, so it
 * works from any page that has the crew in memory. Sized 1080×1350 (4:5), which previews well in
 * Discord, Instagram and most chat apps.
 */

import { saveBlob } from '@/lib/native';

export interface CrewPosterMember {
    name: string;
    cost: number;
    category: string;
}

export interface CrewPosterData {
    name: string;
    masterName: string;
    factionName: string | null;
    /** CSS variable name for the faction hue (e.g. `bayou`), used for the accent band. */
    factionColor: string | null;
    factionLogo: string | null;
    author: string | null;
    encounterSize: number;
    spent: number;
    members: CrewPosterMember[];
}

const W = 1080;
const H = 1350;

const loadImage = (src: string): Promise<HTMLImageElement | null> =>
    new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = src;
    });

/** Resolve `--bayou` ("H S% L%") to a usable hsl() string, falling back to a neutral. */
const factionHue = (token: string | null): string => {
    if (!token || typeof document === 'undefined') return 'hsl(220 10% 40%)';
    const raw = getComputedStyle(document.documentElement).getPropertyValue(`--${token}`).trim();
    return raw ? `hsl(${raw})` : 'hsl(220 10% 40%)';
};

const fit = (ctx: CanvasRenderingContext2D, text: string, maxWidth: number): string => {
    if (ctx.measureText(text).width <= maxWidth) return text;
    let out = text;
    while (out.length > 1 && ctx.measureText(out + '…').width > maxWidth) out = out.slice(0, -1);
    return out + '…';
};

export async function renderCrewPoster(data: CrewPosterData): Promise<Blob> {
    const canvas = document.createElement('canvas');
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('Canvas is not supported in this browser.');

    const accent = factionHue(data.factionColor);
    const font = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';

    // Background + accent wash
    ctx.fillStyle = '#0b0b0d';
    ctx.fillRect(0, 0, W, H);
    const wash = ctx.createLinearGradient(0, 0, W, 0);
    wash.addColorStop(0, accent);
    wash.addColorStop(1, 'rgba(0,0,0,0)');
    ctx.globalAlpha = 0.35;
    ctx.fillStyle = wash;
    ctx.fillRect(0, 0, W, 300);
    ctx.globalAlpha = 1;
    ctx.fillStyle = accent;
    ctx.fillRect(0, 0, 16, H);

    // Header
    let textX = 72;
    if (data.factionLogo) {
        const logo = await loadImage(data.factionLogo);
        if (logo) {
            ctx.drawImage(logo, 72, 64, 140, 140);
            textX = 244;
        }
    }
    ctx.fillStyle = '#ffffff';
    ctx.font = `700 64px ${font}`;
    ctx.fillText(fit(ctx, data.name, W - textX - 72), textX, 128);
    ctx.fillStyle = 'rgba(255,255,255,0.72)';
    ctx.font = `500 34px ${font}`;
    ctx.fillText(fit(ctx, [data.masterName, data.factionName].filter(Boolean).join(' · '), W - textX - 72), textX, 184);

    // Soulstone summary
    ctx.fillStyle = 'rgba(255,255,255,0.55)';
    ctx.font = `600 26px ${font}`;
    ctx.fillText(`${data.encounterSize}ss encounter · ${data.spent}ss spent`, 72, 272);

    // Roster
    const maxRows = 14;
    const rows = data.members.slice(0, maxRows);
    const rowH = Math.min(96, Math.max(66, Math.floor(800 / Math.max(rows.length, 1))));
    let y = 372;
    ctx.font = `600 ${rowH > 80 ? 44 : 38}px ${font}`;
    rows.forEach((m, i) => {
        if (i % 2 === 0) {
            ctx.fillStyle = 'rgba(255,255,255,0.05)';
            ctx.fillRect(56, y - rowH * 0.68, W - 112, rowH);
        }
        const isLeader = m.category === 'leader' || m.category === 'totem';
        ctx.fillStyle = '#ffffff';
        ctx.textAlign = 'left';
        if (isLeader) {
            ctx.fillStyle = accent;
            ctx.fillRect(56, y - rowH * 0.68, 8, rowH);
            ctx.fillStyle = '#ffffff';
        }
        ctx.fillText(fit(ctx, m.name, W - 72 - 72 - 210), 72, y);
        ctx.fillStyle = 'rgba(255,255,255,0.7)';
        ctx.textAlign = 'right';
        ctx.fillText(isLeader ? (m.category === 'leader' ? 'Leader' : 'Totem') : `${m.cost}ss`, W - 72, y);
        y += rowH;
    });
    ctx.textAlign = 'left';
    if (data.members.length > maxRows) {
        ctx.fillStyle = 'rgba(255,255,255,0.55)';
        ctx.font = `500 30px ${font}`;
        ctx.fillText(`+ ${data.members.length - maxRows} more`, 72, y);
    }

    // Footer
    ctx.fillStyle = 'rgba(255,255,255,0.45)';
    ctx.font = `500 28px ${font}`;
    ctx.fillText(data.author ? `by ${data.author}` : '', 72, H - 96);
    ctx.fillStyle = '#ffffff';
    ctx.font = `800 40px ${font}`;
    ctx.textAlign = 'right';
    ctx.fillText('BiggerHat', W - 72, H - 88);
    ctx.fillStyle = 'rgba(255,255,255,0.45)';
    ctx.font = `500 24px ${font}`;
    ctx.fillText('biggerhat.net', W - 72, H - 52);

    return new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('Could not create image.'))), 'image/png'));
}

/** Share via the native sheet where files are supported (phones), otherwise download the PNG. */
export async function shareOrDownload(blob: Blob, filename: string, title: string): Promise<'shared' | 'downloaded'> {
    const file = new File([blob], filename, { type: 'image/png' });
    if (typeof navigator !== 'undefined' && navigator.canShare?.({ files: [file] })) {
        try {
            await navigator.share({ files: [file], title });
            return 'shared';
        } catch (e) {
            if ((e as DOMException).name === 'AbortError') return 'shared';
        }
    }
    saveBlob(blob, filename);
    return 'downloaded';
}
