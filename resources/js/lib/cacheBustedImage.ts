/**
 * Server-rendered card images (Leader/Totem/Crew Card) are written to a
 * fixed, overwritten-in-place filename per entity — the DB column value
 * never changes between regenerations, so nothing inherently busts a
 * browser cache. Every generator writes a paired `*_generated_at` timestamp
 * only when the render actually completes; append it as a `?v=` query
 * param so the browser treats each real regeneration as a new URL.
 */
export function cacheBustedImagePath(path: string | null | undefined, generatedAt: string | null | undefined): string | null {
    if (!path) return null;
    return generatedAt ? `${path}?v=${encodeURIComponent(generatedAt)}` : path;
}
