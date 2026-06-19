import { fetchFontEmbedCSS } from '@/components/CardCreator/utils';

/**
 * Capture a rendered BonanzaSplitCard element to a printer-friendly PNG File.
 *
 * Forces light mode for the duration of the capture (then restores) so the
 * stored image has a white background + dark text + coloured suit borders,
 * regardless of the admin's current theme. Shared by the Loot Card form (save)
 * and the "Regenerate print images" batch.
 */
export async function captureLootCardImage(target: HTMLElement, name: string): Promise<File | null> {
    const root = document.documentElement;
    const wasDark = root.classList.contains('dark');
    if (wasDark) root.classList.remove('dark');
    try {
        const { toPng } = await import('html-to-image');
        const fontEmbedCSS = await fetchFontEmbedCSS();
        const dataUrl = await toPng(target, { pixelRatio: 2, skipFonts: true, fontEmbedCSS });
        const blob = await (await fetch(dataUrl)).blob();
        const filename = `${name || 'loot-card'}.png`.replace(/[^\w.-]+/g, '-');
        return new File([blob], filename, { type: 'image/png' });
    } catch (e) {
        // Best-effort: a font-embed or canvas-taint failure shouldn't block the flow.
        console.error('Loot card image capture failed:', e);
        return null;
    } finally {
        if (wasDark) root.classList.add('dark');
    }
}
