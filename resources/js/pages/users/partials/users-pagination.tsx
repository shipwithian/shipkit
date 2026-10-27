import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { Pagination } from '@/types';

export function UsersPagination({ pagination }: { pagination: Pagination }) {
    if (pagination.last_page <= 1) {
        return null;
    }

    return (
        <div className="flex items-center justify-between gap-4 text-sm">
            <span className="text-muted-foreground">
                Page {pagination.current_page} of {pagination.last_page} ·{' '}
                {pagination.total} users
            </span>
            <div className="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    disabled={pagination.previous_page_url === null}
                    asChild={pagination.previous_page_url !== null}
                >
                    {pagination.previous_page_url !== null ? (
                        <Link href={pagination.previous_page_url}>
                            Previous
                        </Link>
                    ) : (
                        'Previous'
                    )}
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={pagination.next_page_url === null}
                    asChild={pagination.next_page_url !== null}
                >
                    {pagination.next_page_url !== null ? (
                        <Link href={pagination.next_page_url}>Next</Link>
                    ) : (
                        'Next'
                    )}
                </Button>
            </div>
        </div>
    );
}
