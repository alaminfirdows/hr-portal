import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import AnnouncementController from '@/actions/Modules/Announcements/Http/Controllers/AnnouncementController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
import { StatusBadge } from '@/components/portal/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import { destroy, manage } from '@/routes/announcements';
import type { Option, Paginated } from '@/types';
import { OptionChecklist } from '../../components/option-checklist';

type Row = {
    id: number;
    title: string;
    body: string;
    priority: string;
    priority_label: string;
    priority_color: string;
    audience: string;
    audience_label: string;
    publish_at: string;
    expires_at: string | null;
    publish_at_input: string;
    expires_at_input: string | null;
    status: 'scheduled' | 'live' | 'expired';
    target_ids: number[];
    reads_count: number;
};

type Props = {
    announcements: Paginated<Row>;
    options: {
        priorities: Option<string>[];
        audiences: Option<string>[];
        departments: Option<number>[];
        teams: Option<number>[];
        employees: Option<number>[];
    };
};

const STATUS_COLOR: Record<Row['status'], string> = {
    scheduled: 'blue',
    live: 'green',
    expired: 'gray',
};

export default function AnnouncementsManage({ announcements, options }: Props) {
    const [editing, setEditing] = useState<Row | 'new' | null>(null);
    const row = editing === 'new' ? null : editing;

    return (
        <PageBody>
            <Head title="Manage announcements" />

            <PageHeader
                title="Manage announcements"
                description="Draft, schedule and review everything, including expired items."
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus /> New announcement
                    </Button>
                }
            />

            {announcements.data.length === 0 ? (
                <EmptyState
                    icon="megaphone"
                    title="No announcements yet"
                    description="Create the first one."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Title</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Audience</TableHead>
                                <TableHead>Publishes</TableHead>
                                <TableHead>Expires</TableHead>
                                <TableHead>Reads</TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {announcements.data.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell className="font-medium">
                                        {a.title}
                                        {a.priority !== 'normal' && (
                                            <StatusBadge
                                                className="ml-2"
                                                label={a.priority_label}
                                                color={a.priority_color}
                                            />
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={
                                                a.status
                                                    .charAt(0)
                                                    .toUpperCase() +
                                                a.status.slice(1)
                                            }
                                            color={STATUS_COLOR[a.status]}
                                        />
                                    </TableCell>
                                    <TableCell>{a.audience_label}</TableCell>
                                    <TableCell>
                                        {formatDateTime(a.publish_at)}
                                    </TableCell>
                                    <TableCell>
                                        {a.expires_at
                                            ? formatDateTime(a.expires_at)
                                            : '—'}
                                    </TableCell>
                                    <TableCell>{a.reads_count}</TableCell>
                                    <TableCell>
                                        <div className="flex gap-1">
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Edit ${a.title}`}
                                                onClick={() => setEditing(a)}
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        aria-label={`Delete ${a.title}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title="Delete this announcement?"
                                                action={destroy(a.id)}
                                                confirmLabel="Delete"
                                                destructive
                                            />
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PaginationLinks paginator={announcements} />

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {row ? 'Edit announcement' : 'New announcement'}
                        </DialogTitle>
                    </DialogHeader>
                    {editing !== null && (
                        <AnnouncementForm
                            key={row?.id ?? 'new'}
                            row={row}
                            options={options}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}

function AnnouncementForm({
    row,
    options,
    onDone,
}: {
    row: Row | null;
    options: Props['options'];
    onDone: () => void;
}) {
    const [audience, setAudience] = useState(row?.audience ?? 'all');
    const targets: Option<number>[] =
        audience === 'department'
            ? options.departments
            : audience === 'team'
              ? options.teams
              : audience === 'employees'
                ? options.employees
                : [];

    return (
        <Form
            {...(row
                ? AnnouncementController.update.form(row.id)
                : AnnouncementController.store.form())}
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            className="grid gap-4"
        >
            {({ errors, processing }) => (
                <>
                    <FormField
                        label="Title"
                        htmlFor="title"
                        error={errors.title}
                        required
                    >
                        <Input
                            id="title"
                            name="title"
                            defaultValue={row?.title}
                            required
                        />
                    </FormField>
                    <FormField
                        label="Message"
                        htmlFor="body"
                        error={errors.body}
                        required
                    >
                        <Textarea
                            id="body"
                            name="body"
                            rows={6}
                            defaultValue={row?.body}
                            required
                        />
                    </FormField>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Priority"
                            htmlFor="priority"
                            error={errors.priority}
                            required
                        >
                            <OptionSelect
                                id="priority"
                                name="priority"
                                value={row?.priority ?? 'normal'}
                                options={options.priorities}
                            />
                        </FormField>
                        <FormField
                            label="Audience"
                            htmlFor="audience"
                            error={errors.audience}
                            required
                        >
                            <OptionSelect
                                id="audience"
                                name="audience"
                                value={audience}
                                onChange={(v) => setAudience(v ?? 'all')}
                                options={options.audiences}
                            />
                        </FormField>
                        <FormField
                            label="Publish at"
                            htmlFor="publish_at"
                            error={errors.publish_at}
                            hint="Leave empty to publish now."
                        >
                            <Input
                                id="publish_at"
                                name="publish_at"
                                type="datetime-local"
                                defaultValue={row?.publish_at_input}
                            />
                        </FormField>
                        <FormField
                            label="Expires at"
                            htmlFor="expires_at"
                            error={errors.expires_at}
                        >
                            <Input
                                id="expires_at"
                                name="expires_at"
                                type="datetime-local"
                                defaultValue={row?.expires_at_input ?? ''}
                            />
                        </FormField>
                    </div>
                    {audience !== 'all' && (
                        <FormField
                            label="Recipients"
                            error={errors.target_ids}
                            required
                        >
                            <OptionChecklist
                                key={audience}
                                name="target_ids"
                                options={targets}
                                initial={
                                    audience === row?.audience
                                        ? row.target_ids
                                        : []
                                }
                            />
                        </FormField>
                    )}
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {row ? 'Save' : 'Publish'}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

AnnouncementsManage.layout = {
    breadcrumbs: [{ title: 'Manage announcements', href: manage() }],
};
