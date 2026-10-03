import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import TaxSlabController from '@/actions/Modules/Payroll/Http/Controllers/TaxSlabController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
import { PageBody, PageHeader } from '@/components/portal/page-header';
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
import { index } from '@/routes/payroll/tax-slabs';
import { formatMoney } from '../../../lib/money';

type Slab = {
    id: number;
    min_amount: number;
    max_amount: number | null;
    min_input: string | null;
    max_input: string | null;
    rate: string | null;
};

type Props = {
    slabs: Slab[];
    currency: string;
};

export default function TaxSlabsIndex({ slabs, currency }: Props) {
    const [editing, setEditing] = useState<Slab | null>(null);
    const [open, setOpen] = useState(false);

    const startCreate = () => {
        setEditing(null);
        setOpen(true);
    };

    const startEdit = (slab: Slab) => {
        setEditing(slab);
        setOpen(true);
    };

    return (
        <PageBody>
            <Head title="Tax slabs" />

            <PageHeader
                title="Tax slabs"
                description="Monthly progressive brackets. Each rate applies only to the part of taxable pay inside its band."
                actions={
                    <Button onClick={startCreate}>
                        <Plus /> New slab
                    </Button>
                }
            />

            {slabs.length === 0 ? (
                <EmptyState
                    icon="calculator"
                    title="No tax slabs yet"
                    description="Add brackets, then use a tax slab component to deduct tax."
                />
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>From</TableHead>
                                <TableHead>Up to</TableHead>
                                <TableHead className="text-right">
                                    Rate
                                </TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {slabs.map((slab) => (
                                <TableRow key={slab.id}>
                                    <TableCell>
                                        {formatMoney(slab.min_amount, currency)}
                                    </TableCell>
                                    <TableCell>
                                        {slab.max_amount === null
                                            ? 'No limit'
                                            : formatMoney(
                                                  slab.max_amount,
                                                  currency,
                                              )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {slab.rate}%
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Edit slab"
                                                onClick={() => startEdit(slab)}
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Delete slab"
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title="Delete this slab?"
                                                action={TaxSlabController.destroy(
                                                    slab.id,
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
                            {editing ? 'Edit slab' : 'New slab'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? TaxSlabController.update.form(editing.id)
                            : TaxSlabController.store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label={`From (${currency})`}
                                    htmlFor="min_amount"
                                    error={errors.min_amount}
                                    required
                                >
                                    <Input
                                        id="min_amount"
                                        name="min_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        defaultValue={editing?.min_input ?? ''}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label={`Up to (${currency})`}
                                    htmlFor="max_amount"
                                    error={errors.max_amount}
                                    hint="Leave empty for the top band."
                                >
                                    <Input
                                        id="max_amount"
                                        name="max_amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        defaultValue={editing?.max_input ?? ''}
                                    />
                                </FormField>
                                <FormField
                                    label="Rate (%)"
                                    htmlFor="rate"
                                    error={errors.rate}
                                    required
                                >
                                    <Input
                                        id="rate"
                                        name="rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        defaultValue={editing?.rate ?? ''}
                                        required
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing ? 'Save changes' : 'Add slab'}
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

TaxSlabsIndex.layout = {
    breadcrumbs: [{ title: 'Tax slabs', href: index() }],
};
