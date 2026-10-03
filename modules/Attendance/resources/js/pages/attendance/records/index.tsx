import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import AttendanceRecordController from '@/actions/Modules/Attendance/Http/Controllers/AttendanceRecordController';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    FilterSelect,
    SearchInput,
} from '@/components/portal/filters';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/attendance/records';
import type { Option, Paginated } from '@/types';
import type { AttendanceRow } from '../../../types';
import { formatMinutes } from '../../../types';

type Row = AttendanceRow & {
    employee: { id: number; full_name: string; department: string | null };
};

type Filters = {
    from?: string;
    to?: string;
    status?: string;
    department_id?: string;
    search?: string;
};

type Props = {
    records: Paginated<Row>;
    filters: Filters;
    counts: { value: string; label: string; total: number }[];
    can_manage: boolean;
    options: {
        statuses: Option<string>[];
        departments: Option<number>[];
        employees: Option<number>[];
    };
};

type Editing = {
    employee_id: number | null;
    date: string;
    check_in: string | null;
    check_out: string | null;
    status: string | null;
    note: string | null;
};

export default function AttendanceRecordsIndex({
    records,
    filters,
    counts,
    can_manage,
    options,
}: Props) {
    const [editing, setEditing] = useState<Editing | null>(null);

    const patch = (values: Record<string, string | number | null>) =>
        applyFilters(filters, values);

    return (
        <PageBody>
            <Head title="Attendance records" />

            <PageHeader
                title="Attendance records"
                description="Check-ins inside your scope for the selected dates."
                actions={
                    can_manage && (
                        <Button
                            onClick={() =>
                                setEditing({
                                    employee_id: null,
                                    date: filters.to ?? '',
                                    check_in: null,
                                    check_out: null,
                                    status: null,
                                    note: null,
                                })
                            }
                        >
                            <Plus /> Add or correct
                        </Button>
                    )
                }
            />

            <div className="flex flex-wrap gap-2 text-sm">
                {counts.map((c) => (
                    <StatusBadge
                        key={c.value}
                        label={`${c.label}: ${c.total}`}
                        color="gray"
                    />
                ))}
            </div>

            <FilterBar
                active={Boolean(
                    filters.status || filters.department_id || filters.search,
                )}
                onReset={() =>
                    applyFilters({}, { from: filters.from, to: filters.to })
                }
            >
                <SearchInput
                    value={filters.search}
                    onSearch={(v) => patch({ search: v })}
                    placeholder="Search employee…"
                />
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

            {records.data.length === 0 ? (
                <EmptyState
                    icon="clock"
                    title="No records"
                    description="Nothing matches these filters. Days are closed at 23:55."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>In</TableHead>
                                <TableHead>Out</TableHead>
                                <TableHead>Worked</TableHead>
                                <TableHead>Overtime</TableHead>
                                <TableHead>Source</TableHead>
                                {can_manage && <TableHead className="w-12" />}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {records.data.map((r) => (
                                <TableRow key={r.id}>
                                    <TableCell className="font-medium">
                                        {r.employee.full_name}
                                        <div className="text-xs text-muted-foreground">
                                            {r.employee.department}
                                        </div>
                                    </TableCell>
                                    <TableCell>{formatDate(r.date)}</TableCell>
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
                                        {r.source_label}
                                    </TableCell>
                                    {can_manage && (
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${r.employee.full_name} ${r.date}`}
                                                onClick={() =>
                                                    setEditing({
                                                        employee_id:
                                                            r.employee_id,
                                                        date: r.date,
                                                        check_in: r.check_in,
                                                        check_out: r.check_out,
                                                        status: r.status,
                                                        note: r.note,
                                                    })
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PaginationLinks paginator={records} />

            <Dialog
                open={editing !== null}
                onOpenChange={(o) => !o && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add or correct attendance</DialogTitle>
                    </DialogHeader>
                    {editing && (
                        <Form
                            key={`${editing.employee_id}-${editing.date}`}
                            {...AttendanceRecordController.upsert.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormField
                                        label="Employee"
                                        htmlFor="employee_id"
                                        error={errors.employee_id}
                                        required
                                    >
                                        <OptionSelect
                                            id="employee_id"
                                            name="employee_id"
                                            value={editing.employee_id}
                                            options={options.employees}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Date"
                                        htmlFor="date"
                                        error={errors.date}
                                        required
                                    >
                                        <Input
                                            id="date"
                                            name="date"
                                            type="date"
                                            defaultValue={editing.date}
                                            required
                                        />
                                    </FormField>
                                    <div className="grid grid-cols-2 gap-4">
                                        <FormField
                                            label="Check in"
                                            htmlFor="check_in"
                                            error={errors.check_in}
                                        >
                                            <Input
                                                id="check_in"
                                                name="check_in"
                                                type="time"
                                                defaultValue={
                                                    editing.check_in ?? ''
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            label="Check out"
                                            htmlFor="check_out"
                                            error={errors.check_out}
                                        >
                                            <Input
                                                id="check_out"
                                                name="check_out"
                                                type="time"
                                                defaultValue={
                                                    editing.check_out ?? ''
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    <FormField
                                        label="Status"
                                        htmlFor="status"
                                        error={errors.status}
                                        hint="Leave empty to work it out from the times."
                                    >
                                        <OptionSelect
                                            id="status"
                                            name="status"
                                            value={editing.status}
                                            options={options.statuses}
                                            allowEmpty
                                            emptyLabel="From times"
                                        />
                                    </FormField>
                                    <FormField
                                        label="Reason for change"
                                        htmlFor="note"
                                        error={errors.note}
                                        required
                                    >
                                        <Input
                                            id="note"
                                            name="note"
                                            defaultValue={editing.note ?? ''}
                                            maxLength={255}
                                            required
                                        />
                                    </FormField>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}

AttendanceRecordsIndex.layout = {
    breadcrumbs: [{ title: 'Attendance records', href: index() }],
};
