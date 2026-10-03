import { Form, Head } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import EmployeeCompensationController from '@/actions/Modules/Payroll/Http/Controllers/EmployeeCompensationController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { StatusBadge } from '@/components/portal/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/payroll/employees';
import type { Option } from '@/types';
import { formatMoney } from '../../../lib/money';

type Employee = {
    id: number;
    full_name: string;
    employee_code: string;
    joined_on: string;
};

type Compensation = {
    id: number;
    basic_salary: number;
    effective_from: string;
    effective_to: string | null;
    reason: string | null;
};

type AssignableComponent = {
    id: number;
    name: string;
    code: string;
    type_label: string;
    type_color: string;
    calculation_label: string;
    applies_to_all: boolean;
    direct: boolean;
    override: string | null;
};

type Adjustment = {
    id: number;
    period: string;
    type: string;
    type_label: string;
    type_color: string;
    name: string;
    amount: number;
    note: string | null;
};

type Props = {
    employee: Employee;
    compensations: Compensation[];
    components: AssignableComponent[];
    adjustments: Adjustment[];
    options: { types: Option<string>[] };
    currency: string;
    defaultPeriod: string;
};

type Row = { on: boolean; amount: string };

export default function PayrollEmployeeShow({
    employee,
    compensations,
    components,
    adjustments,
    options,
    currency,
    defaultPeriod,
}: Props) {
    const [salaryOpen, setSalaryOpen] = useState(false);
    const [adjustOpen, setAdjustOpen] = useState(false);
    const [rows, setRows] = useState<Record<number, Row>>(() =>
        Object.fromEntries(
            components.map((c) => [
                c.id,
                { on: c.direct, amount: c.override ?? '' },
            ]),
        ),
    );

    const patchRow = (id: number, patch: Partial<Row>) =>
        setRows((current) => ({
            ...current,
            [id]: { ...(current[id] ?? { on: false, amount: '' }), ...patch },
        }));

    const included = components.filter((c) => {
        const row = rows[c.id];

        return c.applies_to_all ? (row?.amount ?? '') !== '' : row?.on;
    });

    const current = compensations.find((c) => c.effective_to === null);

    return (
        <PageBody>
            <Head title={`Payroll — ${employee.full_name}`} />

            <PageHeader
                title={employee.full_name}
                description={`${employee.employee_code} · joined ${formatDate(employee.joined_on)}`}
                actions={
                    <Button onClick={() => setSalaryOpen(true)}>
                        <Plus /> Change salary
                    </Button>
                }
            />

            <section className="grid gap-3">
                <h2 className="text-base font-semibold">Salary history</h2>
                {compensations.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No salary set. This employee is skipped by payroll runs
                        until you add one.
                    </p>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Effective from</TableHead>
                                    <TableHead>Until</TableHead>
                                    <TableHead>Reason</TableHead>
                                    <TableHead className="text-right">
                                        Basic salary
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {compensations.map((c) => (
                                    <TableRow key={c.id}>
                                        <TableCell>
                                            {formatDate(c.effective_from)}
                                        </TableCell>
                                        <TableCell>
                                            {c.effective_to
                                                ? formatDate(c.effective_to)
                                                : 'Current'}
                                        </TableCell>
                                        <TableCell>{c.reason ?? '—'}</TableCell>
                                        <TableCell className="text-right">
                                            {formatMoney(
                                                c.basic_salary,
                                                currency,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </section>

            <section className="grid gap-3">
                <h2 className="text-base font-semibold">Pay components</h2>
                <p className="text-sm text-muted-foreground">
                    Components for everyone apply automatically. Tick optional
                    ones to assign them. An amount overrides the computed value
                    for this employee.
                </p>
                <Form
                    {...EmployeeCompensationController.syncComponents.form(
                        employee.id,
                    )}
                    options={{ preserveScroll: true }}
                    className="grid gap-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="divide-y rounded-lg border">
                                {components.map((c) => (
                                    <div
                                        key={c.id}
                                        className="flex flex-col gap-2 p-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex items-center gap-3">
                                            {c.applies_to_all ? (
                                                <span className="w-4" />
                                            ) : (
                                                <Checkbox
                                                    id={`c-${c.id}`}
                                                    checked={
                                                        rows[c.id]?.on ?? false
                                                    }
                                                    onCheckedChange={(next) =>
                                                        patchRow(c.id, {
                                                            on: next === true,
                                                        })
                                                    }
                                                />
                                            )}
                                            <Label
                                                htmlFor={`c-${c.id}`}
                                                className="font-normal"
                                            >
                                                <span className="font-medium">
                                                    {c.name}
                                                </span>{' '}
                                                <span className="text-xs text-muted-foreground">
                                                    {c.calculation_label}
                                                    {c.applies_to_all
                                                        ? ' · everyone'
                                                        : ''}
                                                </span>
                                            </Label>
                                            <StatusBadge
                                                label={c.type_label}
                                                color={c.type_color}
                                            />
                                        </div>
                                        <Input
                                            aria-label={`Override for ${c.name}`}
                                            placeholder="Override amount"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            className="sm:w-44"
                                            value={rows[c.id]?.amount ?? ''}
                                            onChange={(e) =>
                                                patchRow(c.id, {
                                                    amount: e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                            {included.map((c, i) => (
                                <span key={c.id} className="hidden">
                                    <input
                                        type="hidden"
                                        name={`components[${i}][id]`}
                                        value={c.id}
                                    />
                                    <input
                                        type="hidden"
                                        name={`components[${i}][amount]`}
                                        value={rows[c.id]?.amount ?? ''}
                                    />
                                </span>
                            ))}
                            {Object.values(errors).map((message) => (
                                <p
                                    key={message}
                                    className="text-sm text-destructive"
                                >
                                    {message}
                                </p>
                            ))}
                            <div>
                                <Button type="submit" disabled={processing}>
                                    Save components
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </section>

            <section className="grid gap-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-base font-semibold">
                        One-off adjustments
                    </h2>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setAdjustOpen(true)}
                    >
                        <Plus /> Add adjustment
                    </Button>
                </div>
                {adjustments.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No adjustments. Use these for bonuses or one-time
                        deductions in a single month.
                    </p>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Period</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    <TableHead className="w-12" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {adjustments.map((a) => (
                                    <TableRow key={a.id}>
                                        <TableCell>{a.period}</TableCell>
                                        <TableCell>
                                            <div>{a.name}</div>
                                            {a.note && (
                                                <div className="text-xs text-muted-foreground">
                                                    {a.note}
                                                </div>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                label={a.type_label}
                                                color={a.type_color}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {formatMoney(a.amount, currency)}
                                        </TableCell>
                                        <TableCell>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Remove ${a.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title={`Remove ${a.name}?`}
                                                description="Adjustments in an approved or processed run cannot be removed."
                                                action={EmployeeCompensationController.destroyAdjustment(
                                                    a.id,
                                                )}
                                                confirmLabel="Remove"
                                                destructive
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </section>

            <Dialog open={salaryOpen} onOpenChange={setSalaryOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Change salary</DialogTitle>
                    </DialogHeader>
                    <Form
                        {...EmployeeCompensationController.storeCompensation.form(
                            employee.id,
                        )}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setSalaryOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label={`New basic salary (${currency})`}
                                    htmlFor="basic_salary"
                                    error={errors.basic_salary}
                                    hint={
                                        current
                                            ? `Current: ${formatMoney(current.basic_salary, currency)}`
                                            : undefined
                                    }
                                    required
                                >
                                    <Input
                                        id="basic_salary"
                                        name="basic_salary"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Effective from"
                                    htmlFor="effective_from"
                                    error={errors.effective_from}
                                    hint="Must be after the current salary's start date."
                                    required
                                >
                                    <Input
                                        id="effective_from"
                                        name="effective_from"
                                        type="date"
                                        defaultValue={employee.joined_on}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Reason"
                                    htmlFor="reason"
                                    error={errors.reason}
                                >
                                    <Input id="reason" name="reason" />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Save salary
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog open={adjustOpen} onOpenChange={setAdjustOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add adjustment</DialogTitle>
                    </DialogHeader>
                    <Form
                        {...EmployeeCompensationController.storeAdjustment.form(
                            employee.id,
                        )}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setAdjustOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Period"
                                        htmlFor="period"
                                        error={errors.period}
                                        required
                                    >
                                        <Input
                                            id="period"
                                            name="period"
                                            type="month"
                                            defaultValue={defaultPeriod}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="Type"
                                        htmlFor="type"
                                        error={errors.type}
                                        required
                                    >
                                        <OptionSelect
                                            id="type"
                                            name="type"
                                            value="earning"
                                            options={options.types}
                                        />
                                    </FormField>
                                </div>
                                <FormField
                                    label="Name"
                                    htmlFor="name"
                                    error={errors.name}
                                    required
                                >
                                    <Input id="name" name="name" required />
                                </FormField>
                                <FormField
                                    label={`Amount (${currency})`}
                                    htmlFor="amount"
                                    error={errors.amount}
                                    required
                                >
                                    <Input
                                        id="amount"
                                        name="amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Note"
                                    htmlFor="note"
                                    error={errors.note}
                                >
                                    <Input id="note" name="note" />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Add adjustment
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

PayrollEmployeeShow.layout = {
    breadcrumbs: [{ title: 'Salaries', href: index() }],
};
