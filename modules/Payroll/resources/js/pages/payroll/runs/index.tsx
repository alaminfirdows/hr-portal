import { Form, Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import RunController from '@/actions/Modules/Payroll/Http/Controllers/RunController';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
import { PageBody, PageHeader } from '@/components/portal/page-header';
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
import { index, show } from '@/routes/payroll/runs';
import { formatMoney } from '../../../lib/money';

export type RunSummary = {
    id: number;
    period: string;
    label: string;
    status: string;
    status_label: string;
    status_color: string;
    employee_count: number;
    total_gross: number;
    total_deductions: number;
    total_net: number;
    created_by: string | null;
};

type Props = {
    runs: RunSummary[];
    currency: string;
    defaultPeriod: string;
    maxPeriod: string;
};

export default function RunsIndex({
    runs,
    currency,
    defaultPeriod,
    maxPeriod,
}: Props) {
    const [open, setOpen] = useState(false);

    return (
        <PageBody>
            <Head title="Payroll runs" />

            <PageHeader
                title="Payroll runs"
                description="One run per month: calculate, review, approve, then publish payslips."
                actions={
                    <Button onClick={() => setOpen(true)}>
                        <Plus /> New run
                    </Button>
                }
            />

            {runs.length === 0 ? (
                <EmptyState
                    icon="banknote"
                    title="No payroll runs yet"
                    description="Start a run for a month to calculate payslips."
                />
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Period</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">
                                    Employees
                                </TableHead>
                                <TableHead className="text-right">
                                    Gross
                                </TableHead>
                                <TableHead className="text-right">
                                    Net
                                </TableHead>
                                <TableHead>Created by</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {runs.map((run) => (
                                <TableRow key={run.id}>
                                    <TableCell>
                                        <Link
                                            href={show(run.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {run.label}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={run.status_label}
                                            color={run.status_color}
                                        />
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {run.employee_count}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(run.total_gross, currency)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(run.total_net, currency)}
                                    </TableCell>
                                    <TableCell>
                                        {run.created_by ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New payroll run</DialogTitle>
                    </DialogHeader>
                    <Form
                        {...RunController.store.form()}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Month"
                                    htmlFor="period"
                                    error={errors.period}
                                    required
                                >
                                    <Input
                                        id="period"
                                        name="period"
                                        type="month"
                                        defaultValue={defaultPeriod}
                                        max={maxPeriod}
                                        required
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Create run
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

RunsIndex.layout = {
    breadcrumbs: [{ title: 'Payroll runs', href: index() }],
};
