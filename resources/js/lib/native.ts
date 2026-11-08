/**
 * Single seam for "are we inside the native (Capacitor) shell?" plus the few browser behaviours that differ there.
 * See docs/plans/native-app.md. On the web every helper does exactly what the call sites used to do inline; the
 * native branches get filled in when the Capacitor shell lands, so features don't need touching again.
 */

interface CapacitorGlobal {
    isNativePlatform?: () => boolean;
    getPlatform?: () => string;
}

function capacitor(): CapacitorGlobal | undefined {
    return typeof window === 'undefined' ? undefined : (window as unknown as { Capacitor?: CapacitorGlobal }).Capacitor;
}

export function isNative(): boolean {
    return capacitor()?.isNativePlatform?.() === true;
}

export function nativePlatform(): 'ios' | 'android' | 'web' {
    const platform = capacitor()?.getPlatform?.();
    return platform === 'ios' || platform === 'android' ? platform : 'web';
}

/** Saves a generated file (image, PDF, JSON). Web: anchor download. Native (later): Filesystem + Share. */
export function saveBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    saveUrl(url, filename);
    URL.revokeObjectURL(url);
}

/** Saves something already addressable by URL (object URL or data URL). */
export function saveUrl(url: string, filename: string): void {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

/** Opens a page or generated PDF in a new tab. Native (later): in-app Browser plugin. */
export function openExternal(url: string): void {
    window.open(url, '_blank', 'noopener');
}
