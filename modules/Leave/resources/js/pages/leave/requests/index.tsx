import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import LeaveRequestController from '@/actions/Modules/Leave/Http/Controllers/LeaveRequestController';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    FilterSelect,
} from '@/components/portal/filters';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
import { StatusBadge } from '@/components/portal/status-badge';
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
import { index, show } from '@/routes/leave/requests';
import type { Option, Paginated } from '@/types';
import type { BalanceRow, LeaveRequestRow } from '../../../types';
import { formatDays } from '../../../types';

type TypeOption = Option<number> & {
    allow_half_day: boolean;
    requires_attachment: boolean;
};

type Props = {
    balances: BalanceRow[];
    year: number;
    requests: Paginated<LeaveRequestRow>;
    filters: { status?: string };
    options: {
        statuses: Option<string>[];
        day_parts: Option<string>[];
        types: TypeOption[];
    };
};

export default function LeaveRequestsIndex({
    balances,
    year,
    requests,
    filters,
    options,
}: Props) {
    const [open, setOpen] = useState(false);
    const [typeId, setTypeId] = useState<number | null>(
        options.types[0]?.value ?? null,
    );
    const [dayPart, setDayPart] = useState<string | null>('full');
    const type = options.types.find((t) => t.value === typeId);

    return (
        <PageBody>
            <Head title="My leave" />

            <PageHeader
                title="My leave"
                description={`Balances for ${year} and your requests.`}
                actions={
                    <Button onClick={() => setOpen(true)}>
                        <Plus /> Request leave
                    </Button>
                }
            />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {balances.map((b) => (
                    <Card key={b.type.id}>
                        <CardHeader className="pb-2">
                            <CardTitle className="flex items-center justify-between text-sm font-medium">
                                {b.type.name}
                                <StatusBadge
                                    label={b.type.is_paid ? 'Paid' : 'Unpaid'}
                                    color={b.type.color}
                                />
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-semibold">
                                {formatDays(b.available)}
                                {b.available !== null && (
                                    <span className="ml-1 text-sm font-normal text-muted-foreground">
                                        available
                                    </span>
                                )}
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Used {formatDays(b.used)} · Pending{' '}
                                {formatDays(b.pending)}
                                {b.available !== null &&
                                    ` · Total ${formatDays(b.entitled + b.carried_forward + b.adjustment)}`}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <FilterBar
                active={Boolean(filters.status)}
                onReset={() => applyFilters(filters, { status: null })}
            >
                <FilterSelect
                    value={filters.status}
                    options={options.statuses}
                    placeholder="Status"
                    onChange={(status) => applyFilters(filters, { status })}
                />
            </FilterBar>

            {requests.data.length === 0 ? (
                <EmptyState
                    icon="plane"
                    title="No leave requests"
                    description="Requests you submit appear here."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Type</TableHead>
                                <TableHead>Dates</TableHead>
                                <TableHead>Days</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Waiting for</TableHead>
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
                                            {r.type.name}
                                        </Link>
                                    </TableCell>
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
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PaginationLinks paginator={requests} />

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Request leave</DialogTitle>
                    </DialogHeader>
                    <Form
                        {...LeaveRequestController.store.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Leave type"
                                    htmlFor="leave_type_id"
                                    error={errors.leave_type_id}
                                    required
                                >
                                    <OptionSelect
                                        id="leave_type_id"
                                        name="leave_type_id"
                                        value={typeId}
                                        options={options.types}
                                        onChange={setTypeId}
                                    />
                                </FormField>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="From"
                                        htmlFor="start_date"
                                        error={errors.start_date}
                                        required
                                    >
                                        <Input
                                            id="start_date"
                                            name="start_date"
                                            type="date"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="To"
                                        htmlFor="end_date"
                                        error={errors.end_date}
                                        required
                                    >
                                        <Input
                                            id="end_date"
                                            name="end_date"
                                            type="date"
                                            required
                                        />
                                    </FormField>
                                </div>
                                {type?.allow_half_day ? (
                                    <FormField
                                        label="Day part"
                                        htmlFor="day_part"
                                        error={errors.day_part}
                                        hint="Half days apply to a single working day."
                                    >
                                        <OptionSelect
                                            id="day_part"
                                            name="day_part"
                                            value={dayPart}
                                            options={options.day_parts}
                                            onChange={setDayPart}
                                        />
                                    </FormField>
                                ) : (
                                    <input
                                        type="hidden"
                                        name="day_part"
                                        value="full"
                                    />
                                )}
                                <FormField
                                    label="Reason"
                                    htmlFor="reason"
                                    error={errors.reason}
                                >
                                    <Textarea
                                        id="reason"
                                        name="reason"
                                        rows={3}
                                    />
                                </FormField>
                                <FormField
                                    label="Attachment"
                                    htmlFor="attachment"
                                    error={errors.attachment}
                                    required={type?.requires_attachment}
                                    hint="PDF, JPG or PNG up to 5 MB."
                                >
                                    <Input
                                        id="attachment"
                                        name="attachment"
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png"
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Submit request
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

LeaveRequestsIndex.layout = {
    breadcrumbs: [{ title: 'My leave', href: index() }],
};
