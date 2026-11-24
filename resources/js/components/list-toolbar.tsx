import { Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    /** The search the page was rendered with. */
    search: string | null;
    /** Called with the new search after typing pauses. */
    onSearch: (search: string | null) => void;
    placeholder: string;
    /** Filters beside the search box, such as a `ListFilter`. */
    children?: ReactNode;
};

/**
 * The bar above a list: a search box that searches as you type, and the page's filters.
 */
export function ListToolbar({
    search,
    onSearch,
    placeholder,
    children,
}: Props) {
    const [value, setValue] = useState(search ?? '');
    const last = useRef(search ?? '');
    const latestOnSearch = useRef(onSearch);

    useEffect(() => {
        latestOnSearch.current = onSearch;
    }, [onSearch]);

    useEffect(() => {
        if (value.trim() === last.current) {
            return;
        }

        const timer = window.setTimeout(() => {
            last.current = value.trim();
            latestOnSearch.current(value.trim() === '' ? null : value.trim());
        }, 300);

        return () => window.clearTimeout(timer);
    }, [value]);

    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div className="relative sm:max-w-xs sm:flex-1">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input
                    type="search"
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    placeholder={placeholder}
                    aria-label={placeholder}
                    className="pl-9"
                />
            </div>
            {children}
        </div>
    );
}
