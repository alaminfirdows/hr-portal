import { Head, Link, router } from '@inertiajs/react';
import { CheckCheck, Check, Trash2 } from 'lucide-react';
import { DynamicIcon } from '@/components/portal/dynamic-icon';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { timeAgo } from '@/lib/format';
import { destroy, index, open, read, readAll } from '@/routes/notifications';
import type { Paginated, PortalNotification } from '@/types';

type Filter = 'all' | 'unread';

type Props = {
    items: Paginated<PortalNotification>;
    filter: Filter;
};

export default function NotificationsIndex({ items, filter }: Props) {
    const hasUnread =
        items.data.some((n) => n.read_at === null) || filter === 'unread';

    return (
        <PageBody>
            <Head title="Notifications" />
            <PageHeader
                title="Notifications"
                actions={
                    hasUnread && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck className="size-4" /> Mark all read
                        </Button>
                    )
                }
            />

            <Tabs
                value={filter}
                onValueChange={(v) =>
                    router.get(
                        index({
                            query: v === 'unread' ? { filter: 'unread' } : {},
                        }).url,
                        {},
                        { preserveState: true, replace: true },
                    )
                }
            >
                <TabsList>
                    <TabsTrigger value="all">All</TabsTrigger>
                    <TabsTrigger value="unread">Unread</TabsTrigger>
                </TabsList>
            </Tabs>

            {items.data.length === 0 ? (
                <EmptyState
                    icon="bell"
                    title={
                        filter === 'unread'
                            ? 'No unread notifications'
                            : 'No notifications'
                    }
                    description="You are all caught up."
                />
            ) : (
                <ul className="divide-y rounded-lg border">
                    {items.data.map((n) => (
                        <li
                            key={n.id}
                            className={cn(
                                'flex items-start gap-3 p-4',
                                n.read_at === null && 'bg-muted/40',
                            )}
                        >
                            <DynamicIcon
                                name={n.icon}
                                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            />
                            <div className="min-w-0 flex-1">
                                {n.url ? (
                                    <Link
                                        href={open(n.id).url}
                                        className={cn(
                                            'text-sm hover:underline',
                                            n.read_at === null
                                                ? 'font-semibold'
                                                : 'font-medium',
                                        )}
                                    >
                                        {n.title}
                                    </Link>
                                ) : (
                                    <p
                                        className={cn(
                                            'text-sm',
                                            n.read_at === null
                                                ? 'font-semibold'
                                                : 'font-medium',
                                        )}
                                    >
                                        {n.title}
                                    </p>
                                )}
                                <p className="text-sm text-muted-foreground">
                                    {n.body}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {timeAgo(n.created_at)}
                                </p>
                            </div>
                            <div className="flex shrink-0 gap-1">
                                {n.read_at === null && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Mark as read"
                                        onClick={() =>
                                            router.post(
                                                read(n.id).url,
                                                {},
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <Check className="size-4" />
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Delete notification"
                                    onClick={() =>
                                        router.delete(destroy(n.id).url, {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <PaginationLinks paginator={items} />
        </PageBody>
    );
}

NotificationsIndex.layout = {
    breadcrumbs: [{ title: 'Notifications', href: index() }],
};
