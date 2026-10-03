import { Form, Head, Link } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import LeaveApprovalController from '@/actions/Modules/Leave/Http/Controllers/LeaveApprovalController';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    FilterSelect,
} from '@/components/portal/filters';
import { FormField } from '@/components/portal/form-field';
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
import { formatDate } from '@/lib/format';
import { index } from '@/routes/leave/approvals';
import { show } from '@/routes/leave/requests';
import type { Option, Paginated } from '@/types';
import type { LeaveRequestRow } from '../../../types';
import { formatDays } from '../../../types';

type Row = LeaveRequestRow & { can_act: boolean };

type Filters = {
    status?: string;
    department_id?: string;
    team_id?: string;
    leave_type_id?: string;
    from?: string;
    to?: string;
};

type Props = {
    requests: Paginated<Row>;
    filters: Filters;
    options: {
        statuses: Option<string>[];
        departments: Option<number>[];
        teams: Option<number>[];
        types: Option<number>[];
    };
};

export default function LeaveApprovalsIndex({
    requests,
    filters,
    options,
}: Props) {
    const [deciding, setDeciding] = useState<{
        row: Row;
        mode: 'approve' | 'reject';
    } | null>(null);

    const patch = (values: Record<string, string | number | null>) =>
        applyFilters(filters, values);

    return (
        <PageBody>
            <Head title="Leave approvals" />

            <PageHeader
                title="Leave approvals"
                description="Requests waiting for your decision."
            />

            <FilterBar
                active={Object.entries(filters).some(
                    ([k, v]) => v && !(k === 'status' && v === 'pending'),
                )}
                onReset={() => applyFilters({}, {})}
            >
                <FilterSelect
                    value={filters.status}
                    options={options.statuses}
                    placeholder="Status"
                    onChange={(v) => patch({ status: v })}
                />
                <FilterSelect
                    value={
                        filters.department_id
                            ? Number(filters.department_id)
                            : null
                    }
                    options={options.departments}
                    placeholder="Department"
                    onChange={(v) => patch({ department_id: v })}
                />
                <FilterSelect
                    value={filters.team_id ? Number(filters.team_id) : null}
                    options={options.teams}
                    placeholder="Team"
                    onChange={(v) => patch({ team_id: v })}
                />
                <FilterSelect
                    value={
                        filters.leave_type_id
                            ? Number(filters.leave_type_id)
                            : null
                    }
                    options={options.types}
                    placeholder="Type"
                    onChange={(v) => patch({ leave_type_id: v })}
                />
                <Input
                    type="date"
                    aria-label="From date"
                    className="w-full sm:w-40"
                    defaultValue={filters.from}
                    onChange={(e) => patch({ from: e.target.value })}
                />
                <Input
                    type="date"
                    aria-label="To date"
                    className="w-full sm:w-40"
                    defaultValue={filters.to}
                    onChange={(e) => patch({ to: e.target.value })}
                />
            </FilterBar>

            {requests.data.length === 0 ? (
                <EmptyState
                    icon="calendar-check"
                    title="Nothing to review"
                    description="No requests match these filters."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Dates</TableHead>
                                <TableHead>Days</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Step</TableHead>
                                <TableHead className="w-40" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {requests.data.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={show(r.id)}
                                            className="hover:underline"
                                        >
                                            {r.employee.full_name}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {r.department}
                                        </div>
                                    </TableCell>
                                    <TableCell>{r.type.name}</TableCell>
                                    <TableCell>
                                        {formatDate(r.start_date)}
                                        {r.start_date !== r.end_date &&
                                            ` – ${formatDate(r.end_date)}`}
                                    </TableCell>
                                    <TableCell>{formatDays(r.days)}</TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={r.status_label}
                                            color={r.status_color}
                                        />
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {r.status === 'pending'
                                            ? (r.current_role ?? '—')
                                            : '—'}
                                    </TableCell>
                                    <TableCell>
                                        {r.can_act && (
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        setDeciding({
                                                            row: r,
                                                            mode: 'approve',
                                                        })
                                                    }
                                                >
                                                    <Check /> Approve
                                                </Button>
                                                <Button
                                                    size="icon"
                                                    variant="outline"
                                                    aria-label="Reject"
                                                    onClick={() =>
                                                        setDeciding({
                                                            row: r,
                                                            mode: 'reject',
                                                        })
                                                    }
                                                >
                                                    <X />
                                                </Button>
                                            </div>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PaginationLinks paginator={requests} />

            <Dialog
                open={deciding !== null}
                onOpenChange={(o) => !o && setDeciding(null)}
            >
                <DialogContent>
                    {deciding && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    {deciding.mode === 'reject'
                                        ? 'Reject'
                                        : 'Approve'}{' '}
                                    {deciding.row.employee.full_name}
                                </DialogTitle>
                            </DialogHeader>
                            <Form
                                key={`${deciding.row.id}-${deciding.mode}`}
                                {...(deciding.mode === 'reject'
                                    ? LeaveApprovalController.reject.form(
                                          deciding.row.id,
                                      )
                                    : LeaveApprovalController.approve.form(
                                          deciding.row.id,
                                      ))}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setDeciding(null)}
                                className="grid gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <FormField
                                            label="Comment"
                                            htmlFor="comment"
                                            error={errors.comment}
                                        >
                                            <Textarea
                                                id="comment"
                                                name="comment"
                                                rows={3}
                                            />
                                        </FormField>
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                variant={
                                                    deciding.mode === 'reject'
                                                        ? 'destructive'
                                                        : 'default'
                                                }
                                            >
                                                {deciding.mode === 'reject'
                                                    ? 'Reject'
                                                    : 'Approve'}
                                            </Button>
                                        </DialogFooter>
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}

LeaveApprovalsIndex.layout = {
    breadcrumbs: [{ title: 'Leave approvals', href: index() }],
};
