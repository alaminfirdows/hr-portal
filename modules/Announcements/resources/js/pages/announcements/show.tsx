import { Head } from '@inertiajs/react';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { StatusBadge } from '@/components/portal/status-badge';
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/announcements';

type Props = {
    announcement: {
        id: number;
        title: string;
        body: string;
        author: string | null;
        priority: string;
        priority_label: string;
        priority_color: string;
        audience_label: string;
        publish_at: string;
        expires_at: string | null;
        status: string;
    };
    can: { manage: boolean };
};

export default function AnnouncementShow({ announcement: a, can }: Props) {
    return (
        <PageBody>
            <Head title={a.title} />

            <PageHeader
                title={a.title}
                description={`${a.author ?? 'HR'} · ${formatDateTime(a.publish_at)} · ${a.audience_label}`}
                actions={
                    <>
                        {a.priority !== 'normal' && (
                            <StatusBadge
                                label={a.priority_label}
                                color={a.priority_color}
                            />
                        )}
                        {can.manage && a.status !== 'live' && (
                            <StatusBadge
                                label={
                                    a.status === 'scheduled'
                                        ? 'Scheduled'
                                        : 'Expired'
                                }
                                color="gray"
                            />
                        )}
                    </>
                }
            />

            <Card>
                <CardContent className="space-y-3">
                    <p className="text-sm whitespace-pre-wrap">{a.body}</p>
                    {a.expires_at && (
                        <p className="text-xs text-muted-foreground">
                            Expires {formatDateTime(a.expires_at)}
                        </p>
                    )}
                </CardContent>
            </Card>
        </PageBody>
    );
}

AnnouncementShow.layout = {
    breadcrumbs: [{ title: 'Announcements', href: index() }],
};
