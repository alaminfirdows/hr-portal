import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

function cleanLabel(label: string): string {
    return label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace(/pagination\.(previous|next)/, (_, dir: string) =>
            dir === 'previous' ? '« Previous' : 'Next »',
        );
}

export function PaginationLinks({
    paginator,
}: {
    paginator: Pick<
        Paginated<unknown>,
        'links' | 'from' | 'to' | 'total' | 'last_page'
    >;
}) {
    if (paginator.last_page <= 1) {
        return paginator.total > 0 ? (
            <p className="text-sm text-muted-foreground">
                {paginator.total} result{paginator.total === 1 ? '' : 's'}
            </p>
        ) : null;
    }

    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <p className="text-sm text-muted-foreground">
                Showing {paginator.from}–{paginator.to} of {paginator.total}
            </p>
            <nav className="flex flex-wrap gap-1" aria-label="Pagination">
                {paginator.links.map((link, i) =>
                    link.url ? (
                        <Link
                            key={i}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className={cn(
                                'rounded-md border px-3 py-1 text-sm hover:bg-muted',
                                link.active &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                        >
                            {cleanLabel(link.label)}
                        </Link>
                    ) : (
                        <span
                            key={i}
                            className="rounded-md border px-3 py-1 text-sm text-muted-foreground opacity-50"
                        >
                            {cleanLabel(link.label)}
                        </span>
                    ),
                )}
            </nav>
        </div>
    );
}
