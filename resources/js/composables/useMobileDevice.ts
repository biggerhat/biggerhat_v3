export function isMobileDevice(): boolean {
    return /Mobi|Android/i.test(navigator.userAgent);
}
