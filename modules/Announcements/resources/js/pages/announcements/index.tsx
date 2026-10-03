import { Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
import { StatusBadge } from '@/components/portal/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, manage, show } from '@/routes/announcements';
import type { Paginated } from '@/types';

type AnnouncementSummary = {
    id: number;
    title: string;
    excerpt: string;
    priority: string;
    priority_label: string;
    priority_color: string;
    audience_label: string;
    publish_at: string;
    is_read: boolean;
};

type Props = {
    announcements: Paginated<AnnouncementSummary>;
    unread: number;
    can: { manage: boolean };
};

export default function AnnouncementsIndex({
    announcements,
    unread,
    can,
}: Props) {
    return (
        <PageBody>
            <Head title="Announcements" />

            <PageHeader
                title="Announcements"
                description={
                    unread > 0
                        ? `${unread} unread`
                        : 'News from HR and your company.'
                }
                actions={
                    can.manage && (
                        <Button asChild variant="outline">
                            <Link href={manage()}>Manage announcements</Link>
                        </Button>
                    )
                }
            />

            {announcements.data.length === 0 ? (
                <EmptyState
                    icon="megaphone"
                    title="No announcements"
                    description="Nothing has been published for you yet."
                />
            ) : (
                <div className="space-y-3">
                    {announcements.data.map((a) => (
                        <Card key={a.id}>
                            <CardContent className="space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    {!a.is_read && (
                                        <Badge aria-label="Unread">New</Badge>
                                    )}
                                    {a.priority !== 'normal' && (
                                        <StatusBadge
                                            label={a.priority_label}
                                            color={a.priority_color}
                                        />
                                    )}
                                    <Link
                                        href={show(a.id)}
                                        className={cn(
                                            'hover:underline',
                                            a.is_read
                                                ? 'font-medium'
                                                : 'font-semibold',
                                        )}
                                    >
                                        {a.title}
                                    </Link>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {a.excerpt}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {formatDateTime(a.publish_at)} ·{' '}
                                    {a.audience_label}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}

            <PaginationLinks paginator={announcements} />
        </PageBody>
    );
}

AnnouncementsIndex.layout = {
    breadcrumbs: [{ title: 'Announcements', href: index() }],
};
