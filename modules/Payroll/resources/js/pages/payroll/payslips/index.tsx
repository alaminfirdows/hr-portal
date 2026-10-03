import { Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { index, show } from '@/routes/payroll/payslips';
import { formatMoney } from '../../../lib/money';

type PayslipRow = {
    id: number;
    period_label: string;
    gross: number;
    total_deductions: number;
    net: number;
    published_at: string | null;
};

type Props = {
    payslips: PayslipRow[];
    currency: string;
};

export default function PayslipsIndex({ payslips, currency }: Props) {
    return (
        <PageBody>
            <Head title="My payslips" />

            <PageHeader
                title="My payslips"
                description="Published payslips, newest first."
            />

            {payslips.length === 0 ? (
                <EmptyState
                    icon="receipt"
                    title="No payslips yet"
                    description="Your payslips appear here once payroll is processed."
                />
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Period</TableHead>
                                <TableHead className="text-right">
                                    Gross
                                </TableHead>
                                <TableHead className="text-right">
                                    Deductions
                                </TableHead>
                                <TableHead className="text-right">
                                    Net
                                </TableHead>
                                <TableHead>Published</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {payslips.map((p) => (
                                <TableRow key={p.id}>
                                    <TableCell>
                                        <Link
                                            href={show(p.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {p.period_label}
                                        </Link>
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
                                    <TableCell className="text-right font-medium">
                                        {formatMoney(p.net, currency)}
                                    </TableCell>
                                    <TableCell>
                                        {formatDate(p.published_at)}
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

PayslipsIndex.layout = {
    breadcrumbs: [{ title: 'My payslips', href: index() }],
};
