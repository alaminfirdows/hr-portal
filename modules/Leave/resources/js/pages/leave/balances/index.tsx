import { Form, Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import LeaveBalanceController from '@/actions/Modules/Leave/Http/Controllers/LeaveBalanceController';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    SearchInput,
} from '@/components/portal/filters';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
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
import { index } from '@/routes/leave/balances';
import type { Option, Paginated } from '@/types';
import type { BalanceRow } from '../../../types';
import { formatDays } from '../../../types';

type EmployeeRow = {
    id: number;
    full_name: string;
    balances: BalanceRow[];
};

type Props = {
    employees: Paginated<EmployeeRow>;
    year: number;
    filters: { search?: string };
    types: Option<number>[];
};

export default function LeaveBalancesIndex({
    employees,
    year,
    filters,
    types,
}: Props) {
    const [target, setTarget] = useState<EmployeeRow | null>(null);
    const [typeId, setTypeId] = useState<number | null>(
        types[0]?.value ?? null,
    );

    const goToYear = (value: number) =>
        router.visit(index({ query: { year: value } }).url, {
            preserveScroll: true,
        });

    const columns = employees.data[0]?.balances ?? [];

    return (
        <PageBody>
            <Head title="Leave balances" />

            <PageHeader
                title="Leave balances"
                description="Available days per employee. Adjustments are audited."
                actions={
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Previous year"
                            onClick={() => goToYear(year - 1)}
                        >
                            <ChevronLeft />
                        </Button>
                        <span className="min-w-14 text-center text-sm font-medium tabular-nums">
                            {year}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label="Next year"
                            onClick={() => goToYear(year + 1)}
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                }
            />

            <FilterBar>
                <SearchInput
                    value={filters.search}
                    placeholder="Search employees…"
                    onSearch={(search) => applyFilters(filters, { search })}
                />
            </FilterBar>

            {employees.data.length === 0 ? (
                <EmptyState icon="timer" title="No employees found" />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Employee</TableHead>
                                {columns.map((c) => (
                                    <TableHead key={c.type.id}>
                                        {c.type.name}
                                    </TableHead>
                                ))}
                                <TableHead className="w-12" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {employees.data.map((e) => (
                                <TableRow key={e.id}>
                                    <TableCell className="font-medium">
                                        {e.full_name}
                                    </TableCell>
                                    {e.balances.map((b) => (
                                        <TableCell key={b.type.id}>
                                            {formatDays(b.available)}
                                            <span className="text-xs text-muted-foreground">
                                                {' '}
                                                / used {formatDays(b.used)}
                                            </span>
                                        </TableCell>
                                    ))}
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={`Adjust ${e.full_name}`}
                                            onClick={() => setTarget(e)}
                                        >
                                            <SlidersHorizontal />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PaginationLinks paginator={employees} />

            <Dialog
                open={target !== null}
                onOpenChange={(o) => !o && setTarget(null)}
            >
                <DialogContent>
                    {target && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Adjust balance · {target.full_name}
                                </DialogTitle>
                            </DialogHeader>
                            <Form
                                key={target.id}
                                {...LeaveBalanceController.adjust.form()}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setTarget(null)}
                                className="grid gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="employee_id"
                                            value={target.id}
                                        />
                                        <input
                                            type="hidden"
                                            name="year"
                                            value={year}
                                        />
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
                                                options={types}
                                                onChange={setTypeId}
                                            />
                                        </FormField>
                                        <FormField
                                            label="Days to add (negative removes)"
                                            htmlFor="delta"
                                            error={errors.delta}
                                            required
                                        >
                                            <Input
                                                id="delta"
                                                name="delta"
                                                type="number"
                                                step="0.5"
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            label="Reason"
                                            htmlFor="reason"
                                            error={errors.reason}
                                            required
                                        >
                                            <Input
                                                id="reason"
                                                name="reason"
                                                required
                                            />
                                        </FormField>
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Save adjustment
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

LeaveBalancesIndex.layout = {
    breadcrumbs: [{ title: 'Leave balances', href: index() }],
};
