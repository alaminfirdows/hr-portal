import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import LeaveTypeController from '@/actions/Modules/Leave/Http/Controllers/LeaveTypeController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
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
import { index } from '@/routes/leave/types';
import type { Option } from '@/types';

type LeaveType = {
    id: number;
    name: string;
    code: string;
    color: string;
    is_paid: boolean;
    requires_attachment: boolean;
    allow_half_day: boolean;
    accrual_method: string;
    annual_quota: number | null;
    carry_forward_cap: number;
    prorate_new_joiners: boolean;
    approval_steps: string[];
    max_consecutive_days: number | null;
    is_active: boolean;
};

type Props = {
    types: LeaveType[];
    options: { accrual_methods: Option<string>[]; colors: Option<string>[] };
};

function Toggle({
    name,
    label,
    checked,
}: {
    name: string;
    label: string;
    checked: boolean;
}) {
    return (
        <div className="flex items-center gap-2">
            <input type="hidden" name={name} value="0" />
            <Checkbox
                id={name}
                name={name}
                value="1"
                defaultChecked={checked}
            />
            <Label htmlFor={name}>{label}</Label>
        </div>
    );
}

export default function LeaveTypesIndex({ types, options }: Props) {
    const [editing, setEditing] = useState<LeaveType | null>(null);
    const [open, setOpen] = useState(false);

    return (
        <PageBody>
            <Head title="Leave types" />

            <PageHeader
                title="Leave types"
                description="Quotas, accrual and the approval chain for each type."
                actions={
                    <Button
                        onClick={() => {
                            setEditing(null);
                            setOpen(true);
                        }}
                    >
                        <Plus /> New leave type
                    </Button>
                }
            />

            {types.length === 0 ? (
                <EmptyState icon="calendar-off" title="No leave types yet" />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Type</TableHead>
                                <TableHead>Quota</TableHead>
                                <TableHead>Accrual</TableHead>
                                <TableHead>Approval chain</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {types.map((t) => (
                                <TableRow key={t.id}>
                                    <TableCell>
                                        <StatusBadge
                                            label={`${t.name} (${t.code})`}
                                            color={t.color}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        {t.annual_quota === null
                                            ? 'Unlimited'
                                            : `${t.annual_quota} days`}
                                    </TableCell>
                                    <TableCell className="capitalize">
                                        {t.accrual_method}
                                    </TableCell>
                                    <TableCell>
                                        {t.approval_steps.length === 0
                                            ? 'Auto-approved'
                                            : t.approval_steps
                                                  .map((s) =>
                                                      s === 'hr'
                                                          ? 'HR'
                                                          : 'Manager',
                                                  )
                                                  .join(' → ')}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={
                                                t.is_active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }
                                            color={
                                                t.is_active ? 'green' : 'gray'
                                            }
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${t.name}`}
                                                onClick={() => {
                                                    setEditing(t);
                                                    setOpen(true);
                                                }}
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${t.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title={`Delete ${t.name}?`}
                                                action={LeaveTypeController.destroy(
                                                    t.id,
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
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? `Edit ${editing.name}`
                                : 'New leave type'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? LeaveTypeController.update.form(editing.id)
                            : LeaveTypeController.store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
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
                                        required
                                    >
                                        <Input
                                            id="code"
                                            name="code"
                                            defaultValue={editing?.code}
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="Color"
                                        htmlFor="color"
                                        error={errors.color}
                                        required
                                    >
                                        <OptionSelect
                                            id="color"
                                            name="color"
                                            value={editing?.color ?? 'blue'}
                                            options={options.colors}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Accrual"
                                        htmlFor="accrual_method"
                                        error={errors.accrual_method}
                                        required
                                    >
                                        <OptionSelect
                                            id="accrual_method"
                                            name="accrual_method"
                                            value={
                                                editing?.accrual_method ??
                                                'yearly'
                                            }
                                            options={options.accrual_methods}
                                        />
                                    </FormField>
                                    <FormField
                                        label="Annual quota (days)"
                                        htmlFor="annual_quota"
                                        error={errors.annual_quota}
                                        hint="Leave empty for unlimited."
                                    >
                                        <Input
                                            id="annual_quota"
                                            name="annual_quota"
                                            type="number"
                                            step="0.5"
                                            min="0"
                                            defaultValue={
                                                editing?.annual_quota ?? ''
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        label="Carry forward cap"
                                        htmlFor="carry_forward_cap"
                                        error={errors.carry_forward_cap}
                                    >
                                        <Input
                                            id="carry_forward_cap"
                                            name="carry_forward_cap"
                                            type="number"
                                            step="0.5"
                                            min="0"
                                            defaultValue={
                                                editing?.carry_forward_cap ?? 0
                                            }
                                        />
                                    </FormField>
                                    <FormField
                                        label="Max consecutive days"
                                        htmlFor="max_consecutive_days"
                                        error={errors.max_consecutive_days}
                                    >
                                        <Input
                                            id="max_consecutive_days"
                                            name="max_consecutive_days"
                                            type="number"
                                            min="1"
                                            defaultValue={
                                                editing?.max_consecutive_days ??
                                                ''
                                            }
                                        />
                                    </FormField>
                                </div>

                                <fieldset className="grid gap-2">
                                    <legend className="mb-1 text-sm font-medium">
                                        Approval chain
                                    </legend>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="step_manager"
                                            name="approval_steps[]"
                                            value="manager"
                                            defaultChecked={
                                                editing
                                                    ? editing.approval_steps.includes(
                                                          'manager',
                                                      )
                                                    : true
                                            }
                                        />
                                        <Label htmlFor="step_manager">
                                            Manager
                                        </Label>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="step_hr"
                                            name="approval_steps[]"
                                            value="hr"
                                            defaultChecked={
                                                editing
                                                    ? editing.approval_steps.includes(
                                                          'hr',
                                                      )
                                                    : true
                                            }
                                        />
                                        <Label htmlFor="step_hr">HR</Label>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Manager always acts before HR. None
                                        selected means auto-approval.
                                    </p>
                                </fieldset>

                                <div className="grid gap-2 sm:grid-cols-2">
                                    <Toggle
                                        name="is_paid"
                                        label="Paid leave"
                                        checked={editing?.is_paid ?? true}
                                    />
                                    <Toggle
                                        name="allow_half_day"
                                        label="Allow half days"
                                        checked={
                                            editing?.allow_half_day ?? false
                                        }
                                    />
                                    <Toggle
                                        name="requires_attachment"
                                        label="Requires attachment"
                                        checked={
                                            editing?.requires_attachment ??
                                            false
                                        }
                                    />
                                    <Toggle
                                        name="prorate_new_joiners"
                                        label="Pro-rate new joiners"
                                        checked={
                                            editing?.prorate_new_joiners ?? true
                                        }
                                    />
                                    <Toggle
                                        name="is_active"
                                        label="Active"
                                        checked={editing?.is_active ?? true}
                                    />
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing
                                            ? 'Save changes'
                                            : 'Create type'}
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

LeaveTypesIndex.layout = {
    breadcrumbs: [{ title: 'Leave types', href: index() }],
};
