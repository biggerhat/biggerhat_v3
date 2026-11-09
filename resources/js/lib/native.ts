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

/**
 * Click handler for `<a :href="serverUrl" :download="name">` links. The web keeps the browser's own download; the native
 * shell can't (WKWebView ignores `download`), so it fetches the file and hands it to saveBlob.
 */
export async function onDownloadClick(event: Event, url: string, filename: string): Promise<void> {
    if (!isNative()) return;
    event.preventDefault();
    try {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(String(res.status));
        saveBlob(await res.blob(), filename);
    } catch {
        openExternal(url);
    }
}

/** Safari, and every browser on iOS (they all run WebKit). Not Chrome/Edge/Android. */
export function isWebKit(): boolean {
    if (typeof navigator === 'undefined') return false;
    const ua = navigator.userAgent;
    return /iPad|iPhone|iPod/.test(ua) || (/Safari/.test(ua) && !/Chrome|Chromium|Android|Edg/.test(ua));
}
