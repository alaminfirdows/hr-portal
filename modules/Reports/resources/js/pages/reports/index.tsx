import { Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index } from '@/routes/reports';

type ReportLink = {
    key: string;
    title: string;
    description: string;
    href: string;
};

type Props = {
    groups: { group: string; reports: ReportLink[] }[];
};

export default function ReportsIndex({ groups }: Props) {
    return (
        <PageBody>
            <Head title="Reports" />
            <PageHeader
                title="Reports"
                description="Filter, review and export HR data."
            />

            {groups.length === 0 ? (
                <EmptyState
                    icon="bar-chart-3"
                    title="No reports available"
                    description="You do not have access to any report."
                />
            ) : (
                groups.map(({ group, reports }) => (
                    <section key={group} className="space-y-3">
                        <h2 className="text-sm font-medium text-muted-foreground">
                            {group}
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {reports.map((report) => (
                                <Link key={report.key} href={report.href}>
                                    <Card className="h-full transition-colors hover:bg-muted/50">
                                        <CardHeader>
                                            <CardTitle className="text-base">
                                                {report.title}
                                            </CardTitle>
                                            <CardDescription>
                                                {report.description}
                                            </CardDescription>
                                        </CardHeader>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </section>
                ))
            )}
        </PageBody>
    );
}

ReportsIndex.layout = {
    breadcrumbs: [{ title: 'Reports', href: index() }],
};
