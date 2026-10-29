import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Origin and path of the page being rendered. Reads the Ziggy `location` prop (present on every Inertia
 * response, so it also works during server rendering where `window` doesn't exist), falling back to
 * `window.location`.
 */
export function useCurrentUrl() {
    const page = usePage();

    return computed(() => {
        const location =
            (page.props as { ziggy?: { location?: string } }).ziggy?.location ?? (typeof window === 'undefined' ? null : window.location.href);
        if (!location) return null;

        const url = new URL(location);

        return { origin: url.origin, pathname: url.pathname };
    });
}
