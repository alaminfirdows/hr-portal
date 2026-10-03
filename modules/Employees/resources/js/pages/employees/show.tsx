import { Form, Head, Link } from '@inertiajs/react';
import { Mail, Pencil, Send } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import EmployeeController from '@/actions/Modules/Employees/Http/Controllers/EmployeeController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { PageBody } from '@/components/portal/page-header';
import { StatusBadge } from '@/components/portal/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatDateTime } from '@/lib/format';
import { edit, index, show } from '@/routes/employees';
import type { Option } from '@/types';
import { EmployeeDocuments } from '../../components/employee-documents';
import { PhotoUpload } from '../../components/photo-upload';
import type { EmergencyContact } from '../../components/profile-fields';
import { STATUS_COLORS } from '../../components/status';
import { EmployeeAvatar } from '../../components/employee-avatar';

type Employee = {
    id: number;
    employee_code: string | null;
    full_name: string;
    email: string;
    personal_email: string | null;
    phone: string | null;
    photo_url: string | null;
    department: string | null;
    team: string | null;
    position: string | null;
    location: string | null;
    manager: { id: number; full_name: string } | null;
    status: string;
    status_label: string;
    joined_on: string;
    exited_on: string | null;
    date_of_birth: string | null;
    gender: string | null;
    employment_type: string;
    work_type: string;
    address: string | null;
    emergency_contact: EmergencyContact;
    skills: string[];
    links: string[];
    user: {
        id: number;
        is_active: boolean;
        last_login_at: string | null;
    } | null;
};

type HistoryEntry = {
    id: number;
    field: string;
    field_label: string;
    old: string | null;
    new: string | null;
    reason: string | null;
    effective_on: string;
    changed_by: string | null;
    created_at: string | null;
};

type Props = {
    employee: Employee;
    reports: { id: number; full_name: string }[];
    history: HistoryEntry[];
    statuses: Option<string>[];
    can: {
        update: boolean;
        status: boolean;
        invite: boolean;
        documents: boolean;
    };
};

function humanize(value: string): string {
    return value.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase());
}

function Detail({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{children || '—'}</dd>
        </div>
    );
}

function StatusDialog({
    employee,
    statuses,
}: {
    employee: Employee;
    statuses: Option<string>[];
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" disabled={statuses.length === 0}>
                    Change status
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Change status of {employee.full_name}
                    </DialogTitle>
                </DialogHeader>
                <Form
                    {...EmployeeController.status.form(employee.id)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormField
                                label="New status"
                                htmlFor="status"
                                error={errors.status}
                                required
                            >
                                <OptionSelect
                                    id="status"
                                    name="status"
                                    value={null}
                                    options={statuses}
                                    placeholder="Select status"
                                />
                            </FormField>
                            <FormField
                                label="Reason"
                                htmlFor="reason"
                                error={errors.reason}
                            >
                                <Input
                                    id="reason"
                                    name="reason"
                                    maxLength={255}
                                />
                            </FormField>
                            <FormField
                                label="Effective on"
                                htmlFor="status_effective_on"
                                error={errors.effective_on}
                            >
                                <Input
                                    id="status_effective_on"
                                    name="effective_on"
                                    type="date"
                                />
                            </FormField>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    Change status
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export default function EmployeeShow({
    employee,
    reports,
    history,
    statuses,
    can,
}: Props) {
    const ec = employee.emergency_contact;
    const emergency = [ec.name, ec.relation, ec.phone]
        .filter(Boolean)
        .join(' · ');

    return (
        <PageBody>
            <Head title={employee.full_name} />

            <Card>
                <CardContent className="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <PhotoUpload
                        employeeId={employee.id}
                        name={employee.full_name}
                        url={employee.photo_url}
                        editable={can.update}
                    />
                    <div className="min-w-0 flex-1 space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {employee.full_name}
                            </h1>
                            <StatusBadge
                                label={employee.status_label}
                                color={STATUS_COLORS[employee.status]}
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {[
                                employee.position,
                                employee.department,
                                employee.team,
                            ]
                                .filter(Boolean)
                                .join(' · ') || 'No position'}
                        </p>
                        <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Mail className="size-3.5" /> {employee.email}
                            {employee.employee_code && (
                                <span className="ml-2 font-mono text-xs">
                                    {employee.employee_code}
                                </span>
                            )}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.update && (
                            <Button asChild>
                                <Link href={edit(employee.id)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        )}
                        {can.status && (
                            <StatusDialog
                                key={employee.status}
                                employee={employee}
                                statuses={statuses}
                            />
                        )}
                        {can.invite && (
                            <ConfirmAction
                                trigger={
                                    <Button variant="outline">
                                        <Send />{' '}
                                        {employee.user
                                            ? 'Resend invite'
                                            : 'Invite'}
                                    </Button>
                                }
                                title={`Invite ${employee.full_name}?`}
                                description={`An invitation email will be sent to ${employee.email}.`}
                                action={EmployeeController.invite(employee.id)}
                                confirmLabel="Send invitation"
                            />
                        )}
                    </div>
                </CardContent>
            </Card>

            <Tabs defaultValue="overview">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="reports">
                        Reports ({reports.length})
                    </TabsTrigger>
                    {can.documents && (
                        <TabsTrigger value="documents">Documents</TabsTrigger>
                    )}
                    <TabsTrigger value="history">History</TabsTrigger>
                </TabsList>

                <TabsContent
                    value="overview"
                    className="grid gap-4 lg:grid-cols-2"
                >
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Employment
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Detail label="Department">
                                    {employee.department}
                                </Detail>
                                <Detail label="Team">{employee.team}</Detail>
                                <Detail label="Position">
                                    {employee.position}
                                </Detail>
                                <Detail label="Location">
                                    {employee.location}
                                </Detail>
                                <Detail label="Manager">
                                    {employee.manager && (
                                        <Link
                                            href={show(employee.manager.id)}
                                            className="hover:underline"
                                        >
                                            {employee.manager.full_name}
                                        </Link>
                                    )}
                                </Detail>
                                <Detail label="Employment type">
                                    {humanize(employee.employment_type)}
                                </Detail>
                                <Detail label="Work type">
                                    {humanize(employee.work_type)}
                                </Detail>
                                <Detail label="Joined">
                                    {formatDate(employee.joined_on)}
                                </Detail>
                                {employee.exited_on && (
                                    <Detail label="Exited">
                                        {formatDate(employee.exited_on)}
                                    </Detail>
                                )}
                                {employee.user && (
                                    <Detail label="Last login">
                                        {formatDateTime(
                                            employee.user.last_login_at,
                                        )}
                                    </Detail>
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Personal and contact
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <Detail label="Phone">{employee.phone}</Detail>
                                <Detail label="Personal email">
                                    {employee.personal_email}
                                </Detail>
                                <Detail label="Date of birth">
                                    {employee.date_of_birth &&
                                        formatDate(employee.date_of_birth)}
                                </Detail>
                                <Detail label="Gender">
                                    {employee.gender &&
                                        humanize(employee.gender)}
                                </Detail>
                                <div className="sm:col-span-2">
                                    <Detail label="Address">
                                        {employee.address}
                                    </Detail>
                                </div>
                                <div className="sm:col-span-2">
                                    <Detail label="Emergency contact">
                                        {emergency}
                                    </Detail>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
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
                </TabsContent>

                <TabsContent value="reports">
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
                                            url={null}
                                        />
                                        <span className="text-sm font-medium">
                                            {r.full_name}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </TabsContent>

                {can.documents && (
                    <TabsContent value="documents">
                        <EmployeeDocuments employeeId={employee.id} />
                    </TabsContent>
                )}

                <TabsContent value="history">
                    {history.length === 0 ? (
                        <EmptyState
                            icon="history"
                            title="No changes recorded"
                        />
                    ) : (
                        <ol className="relative ml-2 space-y-4 border-l pl-6">
                            {history.map((h) => (
                                <li key={h.id} className="relative">
                                    <span className="absolute top-1.5 -left-[1.9rem] size-2.5 rounded-full bg-primary" />
                                    <p className="text-sm font-medium">
                                        {h.field_label}
                                    </p>
                                    <p className="text-sm">
                                        <span className="text-muted-foreground">
                                            {h.old ?? '—'}
                                        </span>
                                        {' → '}
                                        <span>{h.new ?? '—'}</span>
                                    </p>
                                    {h.reason && (
                                        <p className="text-sm text-muted-foreground">
                                            Reason: {h.reason}
                                        </p>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Effective {formatDate(h.effective_on)}
                                        {h.changed_by &&
                                            ` · by ${h.changed_by}`}
                                        {h.created_at &&
                                            ` · ${formatDateTime(h.created_at)}`}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}
                </TabsContent>
            </Tabs>
        </PageBody>
    );
}

EmployeeShow.layout = {
    breadcrumbs: [{ title: 'Employees', href: index() }],
};
