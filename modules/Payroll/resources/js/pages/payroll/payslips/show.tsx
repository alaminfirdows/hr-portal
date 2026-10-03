import { Head, Link } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, pdf } from '@/routes/payroll/payslips';
import { show as showRun } from '@/routes/payroll/runs';
import { formatMoney } from '../../../lib/money';

type Line = {
    id: number;
    code: string;
    name: string;
    type: string;
    amount: number;
};

type Payslip = {
    id: number;
    employee: string;
    employee_code: string;
    period_label: string;
    gross: number;
    total_deductions: number;
    net: number;
    net_clamped: boolean;
    working_days: number;
    paid_days: string;
    unpaid_days: string;
    overtime_hours: number;
    published_at: string | null;
};

type Props = {
    payslip: Payslip;
    lines: Line[];
    run: { id: number; status: string };
    currency: string;
    can: { run: boolean };
};

function LineTable({
    title,
    lines,
    total,
    currency,
}: {
    title: string;
    lines: Line[];
    total: number;
    currency: string;
}) {
    return (
        <div className="rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>{title}</TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {lines.map((line) => (
                        <TableRow key={line.id}>
                            <TableCell>{line.name}</TableCell>
                            <TableCell className="text-right">
                                {formatMoney(line.amount, currency)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
                <TableFooter>
                    <TableRow>
                        <TableCell className="font-semibold">Total</TableCell>
                        <TableCell className="text-right font-semibold">
                            {formatMoney(total, currency)}
                        </TableCell>
                    </TableRow>
                </TableFooter>
            </Table>
        </div>
    );
}

export default function PayslipShow({
    payslip,
    lines,
    run,
    currency,
    can,
}: Props) {
    return (
        <PageBody>
            <Head title={`Payslip ${payslip.period_label}`} />

            <PageHeader
                title={`Payslip ${payslip.period_label}`}
                description={`${payslip.employee} · ${payslip.employee_code}`}
                actions={
                    <>
                        {can.run && (
                            <Button variant="outline" asChild>
                                <Link href={showRun(run.id)}>View run</Link>
                            </Button>
                        )}
                        <Button asChild>
                            <a href={pdf.url(payslip.id)}>
                                <Download /> Download PDF
                            </a>
                        </Button>
                    </>
                }
            />

            {payslip.published_at === null && (
                <Alert>
                    <AlertDescription>
                        Not published yet. The employee cannot see this payslip
                        until the run is processed.
                    </AlertDescription>
                </Alert>
            )}

            {payslip.net_clamped && (
                <Alert variant="destructive">
                    <AlertDescription>
                        Deductions exceeded earnings, so net pay was set to
                        zero.
                    </AlertDescription>
                </Alert>
            )}

            <dl className="grid gap-4 text-sm sm:grid-cols-4">
                <div>
                    <dt className="text-muted-foreground">Working days</dt>
                    <dd className="font-medium">{payslip.working_days}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Paid days</dt>
                    <dd className="font-medium">{Number(payslip.paid_days)}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Unpaid days</dt>
                    <dd className="font-medium">
                        {Number(payslip.unpaid_days)}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Overtime hours</dt>
                    <dd className="font-medium">{payslip.overtime_hours}</dd>
                </div>
            </dl>

            <div className="grid gap-4 lg:grid-cols-2">
                <LineTable
                    title="Earnings"
                    lines={lines.filter((l) => l.type === 'earning')}
                    total={payslip.gross}
                    currency={currency}
                />
                <LineTable
                    title="Deductions"
                    lines={lines.filter((l) => l.type === 'deduction')}
                    total={payslip.total_deductions}
                    currency={currency}
                />
            </div>

            <div className="rounded-lg border bg-muted/40 p-4 text-right">
                <div className="text-sm text-muted-foreground">Net pay</div>
                <div className="text-2xl font-semibold">
                    {formatMoney(payslip.net, currency)}
                </div>
            </div>
        </PageBody>
    );
}

PayslipShow.layout = {
    breadcrumbs: [{ title: 'My payslips', href: index() }],
};
