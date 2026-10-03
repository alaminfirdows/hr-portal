import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ComponentController from '@/actions/Modules/Payroll/Http/Controllers/ComponentController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
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
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/payroll/components';
import type { Option } from '@/types';
import { FlagField } from '../../../components/flag-field';

type PayComponent = {
    id: number;
    name: string;
    code: string;
    type: string;
    type_label: string;
    type_color: string;
    calculation: string;
    calculation_label: string;
    value: string | null;
    formula: string | null;
    is_taxable: boolean;
    applies_to_all: boolean;
    is_active: boolean;
    sort: number;
};

type Props = {
    components: PayComponent[];
    options: { types: Option<string>[]; calculations: Option<string>[] };
    variables: string[];
};

function describe(component: PayComponent): string {
    switch (component.calculation) {
        case 'fixed':
            return component.value ?? '—';
        case 'percent_of_basic':
            return `${component.value ?? '0'}% of basic`;
        case 'formula':
            return component.formula ?? '';
        default:
            return 'Progressive slabs on taxable pay';
    }
}

export default function ComponentsIndex({
    components,
    options,
    variables,
}: Props) {
    const [editing, setEditing] = useState<PayComponent | null>(null);
    const [open, setOpen] = useState(false);
    const [calculation, setCalculation] = useState('fixed');

    const startCreate = () => {
        setEditing(null);
        setCalculation('fixed');
        setOpen(true);
    };

    const startEdit = (component: PayComponent) => {
        setEditing(component);
        setCalculation(component.calculation);
        setOpen(true);
    };

    return (
        <PageBody>
            <Head title="Pay components" />

            <PageHeader
                title="Pay components"
                description="Earnings and deductions applied on every payslip. Percent values are of basic salary."
                actions={
                    <Button onClick={startCreate}>
                        <Plus /> New component
                    </Button>
                }
            />

            {components.length === 0 ? (
                <EmptyState
                    icon="calculator"
                    title="No components yet"
                    description="Add house rent, allowances or deductions to build payslips."
                />
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Calculation</TableHead>
                                <TableHead>Applies to</TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {components.map((component) => (
                                <TableRow
                                    key={component.id}
                                    className={
                                        component.is_active
                                            ? undefined
                                            : 'opacity-60'
                                    }
                                >
                                    <TableCell>
                                        <div className="font-medium">
                                            {component.name}
                                        </div>
                                        <div className="font-mono text-xs text-muted-foreground">
                                            {component.code}
                                            {component.is_active
                                                ? ''
                                                : ' · inactive'}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={component.type_label}
                                            color={component.type_color}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div className="text-sm">
                                            {component.calculation_label}
                                        </div>
                                        <div className="line-clamp-1 font-mono text-xs text-muted-foreground">
                                            {describe(component)}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {component.applies_to_all
                                            ? 'Everyone'
                                            : 'Assigned only'}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${component.name}`}
                                                onClick={() =>
                                                    startEdit(component)
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${component.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title={`Delete ${component.name}?`}
                                                description="Existing payslips keep their lines. New runs will no longer include it."
                                                action={ComponentController.destroy(
                                                    component.id,
                                                )}
                                                confirmLabel="Delete"
                                                destructive
                                            />
                                        </div>
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
                        <DialogTitle>
                            {editing ? `Edit ${editing.name}` : 'New component'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? ComponentController.update.form(editing.id)
                            : ComponentController.store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Name"
                                    htmlFor="name"
                                    error={errors.name}
                                    required
                                >
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={editing?.name}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Code"
                                    htmlFor="code"
                                    error={errors.code}
                                    hint="Unique short code, e.g. HRA."
                                    required
                                >
                                    <Input
                                        id="code"
                                        name="code"
                                        defaultValue={editing?.code}
                                        required
                                    />
                                </FormField>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <FormField
                                        label="Type"
                                        htmlFor="type"
                                        error={errors.type}
                                        required
                                    >
                                        <OptionSelect
                                            id="type"
                                            name="type"
                                            value={editing?.type ?? 'earning'}
                                            options={options.types}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Calculation"
                                        htmlFor="calculation"
                                        error={errors.calculation}
                                        required
                                    >
                                        <OptionSelect
                                            id="calculation"
                                            name="calculation"
                                            value={calculation}
                                            options={options.calculations}
                                            onChange={(next) =>
                                                setCalculation(next ?? 'fixed')
                                            }
                                        />
                                    </FormField>
                                </div>
                                {(calculation === 'fixed' ||
                                    calculation === 'percent_of_basic') && (
                                    <FormField
                                        label={
                                            calculation === 'fixed'
                                                ? 'Amount'
                                                : 'Percent of basic'
                                        }
                                        htmlFor="value"
                                        error={errors.value}
                                        required
                                    >
                                        <Input
                                            id="value"
                                            name="value"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            defaultValue={editing?.value ?? ''}
                                            required
                                        />
                                    </FormField>
                                )}
                                {calculation === 'formula' && (
                                    <FormField
                                        label="Formula"
                                        htmlFor="formula"
                                        error={errors.formula}
                                        hint={`Variables: ${variables.join(', ')}. Functions: min, max, round. Amounts are in minor units.`}
                                        required
                                    >
                                        <Textarea
                                            id="formula"
                                            name="formula"
                                            className="font-mono text-xs"
                                            defaultValue={
                                                editing?.formula ?? ''
                                            }
                                            required
                                        />
                                    </FormField>
                                )}
                                <FormField
                                    label="Sort order"
                                    htmlFor="sort"
                                    error={errors.sort}
                                    hint="Lower values are calculated first."
                                >
                                    <Input
                                        id="sort"
                                        name="sort"
                                        type="number"
                                        min="0"
                                        defaultValue={editing?.sort ?? 100}
                                    />
                                </FormField>
                                <div className="grid gap-2">
                                    <FlagField
                                        id="is_taxable"
                                        name="is_taxable"
                                        label="Counts toward taxable pay"
                                        defaultChecked={
                                            editing?.is_taxable ?? false
                                        }
                                    />
                                    <FlagField
                                        id="applies_to_all"
                                        name="applies_to_all"
                                        label="Applies to every employee"
                                        defaultChecked={
                                            editing?.applies_to_all ?? true
                                        }
                                    />
                                    <FlagField
                                        id="is_active"
                                        name="is_active"
                                        label="Active"
                                        defaultChecked={
                                            editing?.is_active ?? true
                                        }
                                    />
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing
                                            ? 'Save changes'
                                            : 'Create component'}
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

ComponentsIndex.layout = {
    breadcrumbs: [{ title: 'Pay components', href: index() }],
};
