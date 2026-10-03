import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import RunController from '@/actions/Modules/Payroll/Http/Controllers/RunController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { StatCard } from '@/components/portal/stat-card';
import { StatusBadge } from '@/components/portal/status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/payroll/runs';
import { show as showPayslip } from '@/routes/payroll/payslips';
import { formatMoney } from '../../../lib/money';
import type { RunSummary } from './index';

type PayslipRow = {
    id: number;
    employee: string;
    employee_code: string;
    basic: number;
    gross: number;
    total_deductions: number;
    net: number;
    net_clamped: boolean;
    paid_days: string;
    working_days: number;
};

type Props = {
    run: RunSummary;
    payslips: PayslipRow[];
    can: {
        calculate: boolean;
        review: boolean;
        approve: boolean;
        process: boolean;
        export: boolean;
    };
    approvalBlocked: boolean;
    currency: string;
};

export default function RunShow({
    run,
    payslips,
    can,
    approvalBlocked,
    currency,
}: Props) {
    const calculated = payslips.length > 0;

    return (
        <PageBody>
            <Head title={`Payroll ${run.label}`} />

            <PageHeader
                title={`Payroll ${run.label}`}
                description={
                    <span className="inline-flex items-center gap-2">
                        <StatusBadge
                            label={run.status_label}
                            color={run.status_color}
                        />
                        Created by {run.created_by ?? 'unknown'}
                    </span>
                }
                actions={
                    <>
                        {can.export && (
                            <Button variant="outline" asChild>
                                <a
                                    href={RunController.exportMethod.url(
                                        run.id,
                                    )}
                                >
                                    <Download /> Export CSV
                                </a>
                            </Button>
                        )}
                        {can.calculate && (
                            <ConfirmAction
                                trigger={
                                    <Button variant="outline">
                                        {calculated
                                            ? 'Recalculate'
                                            : 'Calculate'}
                                    </Button>
                                }
                                title={
                                    calculated
                                        ? 'Recalculate this run?'
                                        : 'Calculate this run?'
                                }
                                description="Existing payslips in this run are replaced."
                                action={RunController.calculate(run.id)}
                                confirmLabel={
                                    calculated ? 'Recalculate' : 'Calculate'
                                }
                            />
                        )}
                        {can.review && (
                            <ConfirmAction
                                trigger={<Button>Mark reviewed</Button>}
                                title="Mark as reviewed?"
                                action={RunController.review(run.id)}
                                confirmLabel="Mark reviewed"
                            />
                        )}
                        {can.approve && (
                            <ConfirmAction
                                trigger={<Button>Approve</Button>}
                                title="Approve this run?"
                                description="Approval locks the run. Payslips stay hidden until it is processed."
                                action={RunController.approve(run.id)}
                                confirmLabel="Approve"
                            />
                        )}
                        {can.process && (
                            <ConfirmAction
                                trigger={<Button>Process and publish</Button>}
                                title="Publish payslips?"
                                description="Every employee gets their payslip and a notification."
                                action={RunController.process(run.id)}
                                confirmLabel="Publish"
                            />
                        )}
                    </>
                }
            />

            {approvalBlocked && (
                <Alert>
                    <AlertDescription>
                        You created this run, so someone else must approve it.
                    </AlertDescription>
                </Alert>
            )}

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    title="Employees"
                    value={String(run.employee_count)}
                />
                <StatCard
                    title="Gross"
                    value={formatMoney(run.total_gross, currency)}
                />
                <StatCard
                    title="Deductions"
                    value={formatMoney(run.total_deductions, currency)}
                />
                <StatCard
                    title="Net pay"
                    value={formatMoney(run.total_net, currency)}
                />
            </div>

            {!calculated ? (
                <EmptyState
                    icon="calculator"
                    title="Not calculated yet"
                    description="Calculate the run to generate a payslip for each employee."
                />
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead className="text-right">
                                    Paid days
                                </TableHead>
                                <TableHead className="text-right">
                                    Basic
                                </TableHead>
                                <TableHead className="text-right">
                                    Gross
                                </TableHead>
                                <TableHead className="text-right">
                                    Deductions
                                </TableHead>
                                <TableHead className="text-right">
                                    Net
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payslips.map((p) => (
                                <TableRow key={p.id}>
                                    <TableCell>
                                        <Link
                                            href={showPayslip(p.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {p.employee}
                                        </Link>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {p.employee_code}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {Number(p.paid_days)} / {p.working_days}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(p.basic, currency)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(p.gross, currency)}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(
                                            p.total_deductions,
                                            currency,
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {formatMoney(p.net, currency)}
                                        {p.net_clamped && (
                                            <div className="text-xs text-destructive">
                                                Clamped to zero
                                            </div>
                                        )}
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

RunShow.layout = {
    breadcrumbs: [{ title: 'Payroll runs', href: index() }],
};
