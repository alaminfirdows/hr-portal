import { Head, Link } from '@inertiajs/react';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    SearchInput,
} from '@/components/portal/filters';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index, show } from '@/routes/payroll/employees';
import type { Paginated } from '@/types';
import { formatMoney } from '../../../lib/money';

type Row = {
    id: number;
    full_name: string;
    employee_code: string;
    position: string | null;
    basic_salary: number | null;
};

type Props = {
    employees: Paginated<Row>;
    filters: { search: string };
    currency: string;
};

export default function PayrollEmployeesIndex({
    employees,
    filters,
    currency,
}: Props) {
    return (
        <PageBody>
            <Head title="Salaries" />

            <PageHeader
                title="Salaries"
                description="Current basic salary per employee. Open one to change salary, components or add adjustments."
            />

            <FilterBar
                active={filters.search !== ''}
                onReset={() => applyFilters({}, {})}
            >
                <SearchInput
                    value={filters.search}
                    onSearch={(v) =>
                        applyFilters({ ...filters }, { search: v })
                    }
                    placeholder="Search employees…"
                />
            </FilterBar>

            {employees.data.length === 0 ? (
                <EmptyState
                    icon="wallet"
                    title="No employees found"
                    description="Try a different search."
                />
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead className="text-right">
                                    Basic salary
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {employees.data.map((employee) => (
                                <TableRow key={employee.id}>
                                    <TableCell>
                                        <Link
                                            href={show(employee.id)}
                                            className="font-medium hover:underline"
                                        >
                                            {employee.full_name}
                                        </Link>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {employee.employee_code}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        {employee.position ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {employee.basic_salary === null
                                            ? 'Not set'
                                            : formatMoney(
                                                  employee.basic_salary,
                                                  currency,
                                              )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PaginationLinks paginator={employees} />
        </PageBody>
    );
}

PayrollEmployeesIndex.layout = {
    breadcrumbs: [{ title: 'Salaries', href: index() }],
};
