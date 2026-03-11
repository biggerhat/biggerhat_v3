/**
 * Extract a human-readable label from a blueprint image URL/path.
 * e.g. "WYR24103-Sonnia-Criid-Unrelenting-Front.png" → "Sonnia Criid Unrelenting (Front)"
 * e.g. "Instruction-WYR20139-GuildInvestigator.jpg" → "Guild Investigator"
 */
export function imageLabel(url: string): string {
    const basename = url.split('/').pop()?.split('?')[0] ?? '';
    const name = basename.replace(/\.[^.]+$/, ''); // strip extension

    // Strip "Instruction-" prefix
    let cleaned = name.replace(/^Instruction-/i, '');

    // Strip WYR SKU prefix
    cleaned = cleaned.replace(/^WYR\d+-?/i, '');

    // Detect Front/Back/Side suffix
    let suffix = '';
    const suffixMatch = cleaned.match(/-(Front|Back|Side-?\d?)$/i);
    if (suffixMatch) {
        suffix = suffixMatch[1];
        cleaned = cleaned.replace(/-(Front|Back|Side-?\d?)$/i, '');
    }

    // Replace hyphens and plus signs with spaces
    cleaned = cleaned.replace(/[-+]/g, ' ').trim();

    if (!cleaned) {
        return basename;
    }

    return suffix ? `${cleaned} (${suffix})` : cleaned;
}

/**
 * Resolve a blueprint image path to a full URL.
 * CDN URLs are returned as-is, local paths get the /storage/ prefix.
 */
export function imageSrc(path: string): string {
    if (path.startsWith('http')) {
        return path;
    }
    return `/storage/${path}`;
}
