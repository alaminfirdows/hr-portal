import { Form, Head, Link } from '@inertiajs/react';
import { AlertTriangle, Archive, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ProjectController from '@/actions/Modules/Projects/Http/Controllers/ProjectController';
import ProjectMemberController from '@/actions/Modules/Projects/Http/Controllers/ProjectMemberController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { StatusBadge } from '@/components/portal/status-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDate, formatDateTime, initials } from '@/lib/format';
import { edit, index } from '@/routes/projects';
import type { Option } from '@/types';
import type { FieldRow, ProjectRecord } from '../../components/types';

type Member = {
    id: number;
    employee_id: number;
    full_name: string;
    photo_url: string | null;
    position: string | null;
    role: string;
    role_label: string;
    allocation_percent: number;
    joined_on: string;
    is_manager: boolean;
    editable: boolean;
    can_remove: boolean;
    allocation_total: number | null;
};

type Activity = {
    id: number;
    action: string;
    description: string;
    actor: string | null;
    created_at: string;
};

type Props = {
    project: ProjectRecord;
    members: Member[];
    fields: FieldRow[];
    activity: Activity[];
    options: { roles: Option<string>[]; employees: Option<number>[] };
    can: { update: boolean; archive: boolean; members: boolean };
    allocationLimit: number;
};

function Detail({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}

export default function ProjectShow({
    project,
    members,
    fields,
    activity,
    options,
    can,
    allocationLimit,
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editing, setEditing] = useState<Member | null>(null);

    const startAdd = () => {
        setEditing(null);
        setDialogOpen(true);
    };

    const startEdit = (member: Member) => {
        setEditing(member);
        setDialogOpen(true);
    };

    return (
        <PageBody>
            <Head title={project.name} />

            <PageHeader
                title={project.name}
                description={
                    <span className="flex flex-wrap items-center gap-2">
                        <span className="font-mono">{project.code}</span>
                        <StatusBadge
                            label={project.status_label}
                            color={project.status_color}
                        />
                        <StatusBadge
                            label={project.visibility_label}
                            color={project.visibility_color}
                        />
                    </span>
                }
                actions={
                    <>
                        {can.update && (
                            <Button variant="outline" asChild>
                                <Link href={edit(project.id)}>
                                    <Pencil /> Edit
                                </Link>
                            </Button>
                        )}
                        {can.archive && (
                            <ConfirmAction
                                trigger={
                                    <Button variant="outline">
                                        <Archive /> Archive
                                    </Button>
                                }
                                title={`Archive ${project.name}?`}
                                description="Archived projects are hidden from the list and no longer count towards allocation."
                                action={ProjectController.archive(project.id)}
                                confirmLabel="Archive"
                                destructive
                            />
                        )}
                    </>
                }
            />

            <Tabs defaultValue="overview">
                <TabsList>
                    <TabsTrigger value="overview">Overview</TabsTrigger>
                    <TabsTrigger value="members">
                        Members ({members.length})
                    </TabsTrigger>
                    <TabsTrigger value="fields">Fields</TabsTrigger>
                    <TabsTrigger value="activity">Activity</TabsTrigger>
                </TabsList>

                <TabsContent value="overview" className="mt-4">
                    <Card>
                        <CardContent className="grid gap-6 p-6">
                            {project.description && (
                                <p className="text-sm whitespace-pre-line">
                                    {project.description}
                                </p>
                            )}
                            <dl className="grid gap-4 sm:grid-cols-3">
                                <Detail label="Manager">
                                    {project.manager ?? '—'}
                                </Detail>
                                <Detail label="Client">
                                    {project.client ?? '—'}
                                </Detail>
                                <Detail label="Priority">
                                    {project.priority_label}
                                </Detail>
                                <Detail label="Start date">
                                    {formatDate(project.start_date)}
                                </Detail>
                                <Detail label="End date">
                                    {formatDate(project.end_date)}
                                </Detail>
                                {project.budget !== null && (
                                    <Detail label="Budget">
                                        {project.budget.toLocaleString(
                                            undefined,
                                            { minimumFractionDigits: 2 },
                                        )}
                                    </Detail>
                                )}
                            </dl>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="members" className="mt-4 grid gap-4">
                    {can.members && (
                        <div className="flex justify-end">
                            <Button onClick={startAdd}>
                                <Plus /> Add member
                            </Button>
                        </div>
                    )}
                    {members.length === 0 ? (
                        <EmptyState
                            icon="users"
                            title="No members yet"
                            description="Add people who work on this project."
                        />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Member</TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead className="text-right">
                                            Allocation
                                        </TableHead>
                                        <TableHead>Joined</TableHead>
                                        <TableHead className="w-24" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {members.map((m) => (
                                        <TableRow key={m.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="size-8">
                                                        {m.photo_url && (
                                                            <AvatarImage
                                                                src={
                                                                    m.photo_url
                                                                }
                                                                alt={
                                                                    m.full_name
                                                                }
                                                            />
                                                        )}
                                                        <AvatarFallback>
                                                            {initials(
                                                                m.full_name,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <span>
                                                        <span className="block font-medium">
                                                            {m.full_name}
                                                            {m.is_manager && (
                                                                <span className="ml-2 text-xs text-muted-foreground">
                                                                    Manager
                                                                </span>
                                                            )}
                                                        </span>
                                                        <span className="block text-xs text-muted-foreground">
                                                            {m.position ?? '—'}
                                                        </span>
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {m.role_label}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <span>
                                                    {m.allocation_percent}%
                                                </span>
                                                {m.allocation_total !== null &&
                                                    m.allocation_total >
                                                        allocationLimit && (
                                                        <span
                                                            className="ml-2 inline-flex items-center gap-1 text-xs text-amber-600"
                                                            title={`Allocated ${m.allocation_total}% across active projects`}
                                                        >
                                                            <AlertTriangle className="size-3" />
                                                            {m.allocation_total}
                                                            % total
                                                        </span>
                                                    )}
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(m.joined_on)}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end">
                                                    {m.editable && (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Edit ${m.full_name}`}
                                                            onClick={() =>
                                                                startEdit(m)
                                                            }
                                                        >
                                                            <Pencil />
                                                        </Button>
                                                    )}
                                                    {m.can_remove && (
                                                        <ConfirmAction
                                                            trigger={
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Remove ${m.full_name}`}
                                                                >
                                                                    <Trash2 />
                                                                </Button>
                                                            }
                                                            title={`Remove ${m.full_name}?`}
                                                            action={ProjectMemberController.destroy(
                                                                {
                                                                    project:
                                                                        project.id,
                                                                    member: m.id,
                                                                },
                                                            )}
                                                            confirmLabel="Remove"
                                                            destructive
                                                        />
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </TabsContent>

                <TabsContent value="fields" className="mt-4">
                    {fields.length === 0 ? (
                        <EmptyState
                            icon="folders"
                            title="No custom fields"
                            description="HR can define extra project metadata."
                        />
                    ) : (
                        <Card>
                            <CardContent className="p-6">
                                <dl className="grid gap-4 sm:grid-cols-2">
                                    {fields.map((f) => (
                                        <Detail key={f.key} label={f.label}>
                                            {f.value === null ||
                                            f.value === '' ? (
                                                '—'
                                            ) : f.type === 'url' ? (
                                                <a
                                                    href={f.value}
                                                    target="_blank"
                                                    rel="noreferrer noopener"
                                                    className="text-primary underline"
                                                >
                                                    {f.value}
                                                </a>
                                            ) : f.type === 'date' ? (
                                                formatDate(f.value)
                                            ) : (
                                                f.value
                                            )}
                                        </Detail>
                                    ))}
                                </dl>
                            </CardContent>
                        </Card>
                    )}
                </TabsContent>

                <TabsContent value="activity" className="mt-4">
                    {activity.length === 0 ? (
                        <EmptyState
                            icon="scroll-text"
                            title="No activity yet"
                        />
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Recent activity
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="grid gap-3">
                                    {activity.map((a) => (
                                        <li key={a.id} className="text-sm">
                                            <span>{a.description}</span>
                                            <span className="block text-xs text-muted-foreground">
                                                {a.actor ?? 'System'} ·{' '}
                                                {formatDateTime(a.created_at)}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>
                    )}
                </TabsContent>
            </Tabs>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? `Edit ${editing.full_name}`
                                : 'Add member'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? ProjectMemberController.update.form({
                                  project: project.id,
                                  member: editing.id,
                              })
                            : ProjectMemberController.store.form(project.id))}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setDialogOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                {!editing && (
                                    <FormField
                                        label="Employee"
                                        htmlFor="employee_id"
                                        error={errors.employee_id}
                                        required
                                    >
                                        <OptionSelect
                                            id="employee_id"
                                            name="employee_id"
                                            value={null}
                                            options={options.employees}
                                            placeholder="Select employee"
                                        />
                                    </FormField>
                                )}
                                <FormField
                                    label="Role"
                                    htmlFor="role"
                                    error={errors.role}
                                    required
                                >
                                    <OptionSelect
                                        id="role"
                                        name="role"
                                        value={editing?.role ?? 'member'}
                                        options={options.roles}
                                    />
                                </FormField>
                                <FormField
                                    label="Allocation (%)"
                                    htmlFor="allocation_percent"
                                    error={errors.allocation_percent}
                                    hint="Share of working time. Over 100% across projects shows a warning."
                                    required
                                >
                                    <Input
                                        id="allocation_percent"
                                        name="allocation_percent"
                                        type="number"
                                        min={0}
                                        max={100}
                                        defaultValue={
                                            editing?.allocation_percent ?? 100
                                        }
                                        required
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing
                                            ? 'Save changes'
                                            : 'Add member'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}

ProjectShow.layout = {
    breadcrumbs: [{ title: 'Projects', href: index() }],
};
