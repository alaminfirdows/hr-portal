import { Form, Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, LogIn, LogOut } from 'lucide-react';
import MyAttendanceController from '@/actions/Modules/Attendance/Http/Controllers/MyAttendanceController';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { StatCard } from '@/components/portal/stat-card';
import { StatusBadge } from '@/components/portal/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { my } from '@/routes/attendance';
import type { AttendanceRow, ShiftRow } from '../../types';
import { formatMinutes } from '../../types';

type Props = {
    today: AttendanceRow | null;
    shift: ShiftRow;
    month: string;
    label: string;
    previous: string;
    next: string;
    records: AttendanceRow[];
    stats: {
        present: number;
        half_day: number;
        absent: number;
        on_leave: number;
        late: number;
        overtime_minutes: number;
    };
};

export default function MyAttendance({
    today,
    shift,
    label,
    previous,
    next,
    records,
    stats,
}: Props) {
    const checkedIn = today?.check_in != null;
    const checkedOut = today?.check_out != null;

    return (
        <PageBody>
            <Head title="My attendance" />

            <PageHeader
                title="My attendance"
                description="Check in when you start and check out when you finish."
            />

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Today</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="grid gap-1 text-sm">
                        <div>
                            Shift: <strong>{shift.name}</strong>,{' '}
                            {shift.start_time} – {shift.end_time} (grace{' '}
                            {shift.grace_minutes} min)
                        </div>
                        <div className="text-muted-foreground">
                            {checkedIn
                                ? `Checked in at ${today?.check_in}${
                                      today?.is_late
                                          ? ` (${today.late_minutes} min late)`
                                          : ''
                                  }`
                                : 'Not checked in yet.'}
                            {checkedOut &&
                                ` Checked out at ${today?.check_out}.`}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {!checkedIn && (
                            <Form
                                {...MyAttendanceController.checkIn.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        <LogIn /> Check in
                                    </Button>
                                )}
                            </Form>
                        )}
                        {checkedIn && !checkedOut && (
                            <Form
                                {...MyAttendanceController.checkOut.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <LogOut /> Check out
                                    </Button>
                                )}
                            </Form>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold">{label}</h2>
                <div className="flex gap-1">
                    <Button variant="outline" size="icon" asChild>
                        <Link
                            href={my({ query: { month: previous } })}
                            aria-label="Previous month"
                            preserveScroll
                        >
                            <ChevronLeft />
                        </Link>
                    </Button>
                    <Button variant="outline" size="icon" asChild>
                        <Link
                            href={my({ query: { month: next } })}
                            aria-label="Next month"
                            preserveScroll
                        >
                            <ChevronRight />
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
                <StatCard title="Present" value={stats.present} />
                <StatCard title="Half days" value={stats.half_day} />
                <StatCard title="Absent" value={stats.absent} />
                <StatCard title="On leave" value={stats.on_leave} />
                <StatCard title="Late" value={stats.late} />
                <StatCard
                    title="Overtime"
                    value={formatMinutes(stats.overtime_minutes)}
                />
            </div>

            {records.length === 0 ? (
                <EmptyState
                    icon="clock"
                    title="No records this month"
                    description="Records appear after you check in or the day is closed."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>In</TableHead>
                                <TableHead>Out</TableHead>
                                <TableHead>Worked</TableHead>
                                <TableHead>Overtime</TableHead>
                                <TableHead>Note</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {records.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell>
                                        {formatDate(r.date)}{' '}
                                        <span className="text-muted-foreground">
                                            {r.weekday}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={r.status_label}
                                            color={r.status_color}
                                        />
                                        {r.is_late && (
                                            <span className="ml-2 text-xs text-amber-600">
                                                {r.late_minutes}m late
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>{r.check_in ?? '—'}</TableCell>
                                    <TableCell>{r.check_out ?? '—'}</TableCell>
                                    <TableCell>
                                        {formatMinutes(r.worked_minutes)}
                                    </TableCell>
                                    <TableCell>
                                        {formatMinutes(r.overtime_minutes)}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {r.note ?? ''}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </PageBody>
    );
}

MyAttendance.layout = {
    breadcrumbs: [{ title: 'My attendance', href: my() }],
};
