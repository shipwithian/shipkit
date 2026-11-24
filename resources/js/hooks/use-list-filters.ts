import { router } from '@inertiajs/react';

type FilterValue = string | number | null;

/**
 * A list page's filters, kept in the URL so a filtered list can be shared, bookmarked, and paged.
 * Changing one reloads the page's props in place, from the first page, and keeps the others.
 */
export function useListFilters<Filters extends Record<string, FilterValue>>(
    filters: Filters,
) {
    const apply = (changes: Partial<Filters>) => {
        const query = Object.fromEntries(
            Object.entries({ ...filters, ...changes }).filter(
                ([, value]) => value !== null && value !== '',
            ),
        );

        router.get(window.location.pathname, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return { apply };
}
