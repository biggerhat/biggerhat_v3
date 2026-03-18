import { cleanObject } from '@/composables/CleanObject';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, type Ref, ref } from 'vue';

interface UseListFilteringOptions {
    /** Route name to filter against */
    routeName: string;
    /** Route parameters (e.g. slug) passed to the route() helper */
    routeParams?: Record<string, unknown> | string;
    /** Keys of the filter params (excluding page_view) */
    filterKeys: readonly string[];
    /** Inertia `only` keys to reload */
    only: string[];
    /** Default page_view value */
    defaultView?: string;
}

export function useListFiltering<T extends Record<string, string | null>>(initialParams: T, options: UseListFilteringOptions) {
    const filterParams = ref({ ...initialParams }) as Ref<T & { page_view: string | null; name_search: string | null }>;

    const activeFilterCount = computed(() => {
        return options.filterKeys.filter((key) => {
            const val = (filterParams.value as Record<string, string | null>)[key];
            return val != null && val !== '';
        }).length;
    });

    const filter = () => {
        const params: Record<string, string | null> = { ...filterParams.value };
        for (const key in params) {
            if (params[key] === '') {
                params[key] = null;
            }
        }
        params.page = null;
        router.get(route(options.routeName, options.routeParams), cleanObject(params), {
            only: options.only,
            replace: true,
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clear = () => {
        for (const key of options.filterKeys) {
            (filterParams.value as Record<string, string | null>)[key] = null;
        }
        filterParams.value.page_view = options.defaultView ?? 'cards';
        filter();
    };

    const handleNameKeydown = (e: KeyboardEvent) => {
        if (e.key === 'Enter') {
            filter();
        }
    };

    const clearNameSearch = () => {
        filterParams.value.name_search = null;
        filter();
    };

    const handleViewChange = (value: string) => {
        filterParams.value.page_view = value;
        filter();
    };

    const isLoading = ref(false);

    onMounted(() => {
        // Hydrate from URL params
        const urlParams = new URLSearchParams(window.location.search);
        for (const key of [...options.filterKeys, 'page_view', 'name_search']) {
            const val = urlParams.get(key);
            if (val !== null) {
                (filterParams.value as Record<string, string | null>)[key] = val;
            }
        }
        if (!filterParams.value.page_view) {
            filterParams.value.page_view = options.defaultView ?? 'cards';
        }

        router.on('start', () => {
            isLoading.value = true;
        });
        router.on('finish', () => {
            isLoading.value = false;
        });
    });

    return {
        filterParams,
        activeFilterCount,
        filter,
        clear,
        handleNameKeydown,
        clearNameSearch,
        handleViewChange,
        isLoading,
    };
}
