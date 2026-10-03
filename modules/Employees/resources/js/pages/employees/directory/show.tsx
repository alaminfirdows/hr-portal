import { Head, Link } from '@inertiajs/react';
import { Mail, Phone } from 'lucide-react';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody } from '@/components/portal/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { directory, show as showRecord } from '@/routes/employees';
import { show } from '@/routes/employees/directory';
import { EmployeeAvatar } from '../../../components/employee-avatar';

type Props = {
    employee: {
        id: number;
        full_name: string;
        email: string;
        phone: string | null;
        photo_url: string | null;
        department: string | null;
        team: string | null;
        position: string | null;
        location: string | null;
        manager: { id: number; full_name: string } | null;
        work_type: string;
        skills: string[];
        links: string[];
    };
    reports: {
        id: number;
        full_name: string;
        position: string | null;
        photo_url: string | null;
    }[];
    canViewRecord: boolean;
};

export default function DirectoryShow({
    employee,
    reports,
    canViewRecord,
}: Props) {
    return (
        <PageBody>
            <Head title={employee.full_name} />

            <Card>
                <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <EmployeeAvatar
                        name={employee.full_name}
                        url={employee.photo_url}
                        className="size-20 text-xl"
                    />
                    <div className="min-w-0 flex-1 space-y-1">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {employee.full_name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {[
                                employee.position,
                                employee.department,
                                employee.team,
                            ]
                                .filter(Boolean)
                                .join(' · ') || '—'}
                        </p>
                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <a
                                href={`mailto:${employee.email}`}
                                className="flex items-center gap-1.5 hover:underline"
                            >
                                <Mail className="size-3.5" /> {employee.email}
                            </a>
                            {employee.phone && (
                                <a
                                    href={`tel:${employee.phone}`}
                                    className="flex items-center gap-1.5 hover:underline"
                                >
                                    <Phone className="size-3.5" />{' '}
                                    {employee.phone}
                                </a>
                            )}
                            {employee.location && (
                                <span>{employee.location}</span>
                            )}
                            <span>{employee.work_type}</span>
                        </div>
                    </div>
                    {canViewRecord && (
                        <Button asChild variant="outline">
                            <Link href={showRecord(employee.id)}>
                                View full record
                            </Link>
                        </Button>
                    )}
                </CardContent>
            </Card>

            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Manager</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {employee.manager ? (
                            <Link
                                href={show(employee.manager.id)}
                                className="flex items-center gap-3 hover:underline"
                            >
                                <EmployeeAvatar
                                    name={employee.manager.full_name}
                                    url={null}
                                />
                                <span className="text-sm font-medium">
                                    {employee.manager.full_name}
                                </span>
                            </Link>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No manager.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Skills and links
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        <div className="flex flex-wrap gap-1.5">
                            {employee.skills.length === 0 && (
                                <span className="text-sm text-muted-foreground">
                                    No skills listed.
                                </span>
                            )}
                            {employee.skills.map((s) => (
                                <Badge key={s} variant="secondary">
                                    {s}
                                </Badge>
                            ))}
                        </div>
                        <ul className="grid gap-1 text-sm">
                            {employee.links.map((l) => (
                                <li key={l}>
                                    <a
                                        href={l}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="text-primary hover:underline"
                                    >
                                        {l}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <section className="grid gap-3">
                <h2 className="text-base font-semibold">
                    Direct reports ({reports.length})
                </h2>
                {reports.length === 0 ? (
                    <EmptyState icon="users" title="No direct reports" />
                ) : (
                    <ul className="divide-y rounded-lg border">
                        {reports.map((r) => (
                            <li key={r.id}>
                                <Link
                                    href={show(r.id)}
                                    className="flex items-center gap-3 p-3 hover:bg-muted/50"
                                >
                                    <EmployeeAvatar
                                        name={r.full_name}
                                        url={r.photo_url}
                                    />
                                    <span>
                                        <span className="block text-sm font-medium">
                                            {r.full_name}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {r.position ?? '—'}
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </PageBody>
    );
}

DirectoryShow.layout = {
    breadcrumbs: [{ title: 'Directory', href: directory() }],
};
