import { isNative, nativePlatform } from '@/lib/native';

/** Reactive-free on purpose: the platform never changes during a page's life. */
export function useNative() {
    return { isNative: isNative(), platform: nativePlatform() };
}
