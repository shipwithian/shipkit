import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination';
import type { Pagination as PaginationProps } from '@/types';

type Props = {
    pagination: PaginationProps;
    /** What the list holds, plural, such as "users". */
    noun: string;
};

/**
 * Where a paginated list is, and the way to the pages around it. The links keep the filters,
 * because the Query paginates with `withQueryString()`.
 */
export function ListPagination({ pagination, noun }: Props) {
    if (pagination.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex flex-col items-center gap-2 text-sm sm:flex-row sm:justify-between">
            <span className="text-muted-foreground">
                Page {pagination.current_page} of {pagination.last_page} ·{' '}
                {pagination.total} {noun}
            </span>
            <Pagination className="mx-0 w-auto">
                <PaginationContent>
                    {pagination.previous_page_url !== null && (
                        <PaginationItem>
                            <PaginationPrevious
                                href={pagination.previous_page_url}
                            />
                        </PaginationItem>
                    )}
                    {pagination.next_page_url !== null && (
                        <PaginationItem>
                            <PaginationNext href={pagination.next_page_url} />
                        </PaginationItem>
                    )}
                </PaginationContent>
            </Pagination>
        </div>
    );
}
