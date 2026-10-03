import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import ShiftController from '@/actions/Modules/Attendance/Http/Controllers/ShiftController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
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
import { index } from '@/routes/attendance/shifts';
import type { Option } from '@/types';
import type { ShiftRow } from '../../../types';

type Shift = ShiftRow & { id: number; assigned: number };

type Props = {
    shifts: Shift[];
    employees: Option<number>[];
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

export default function ShiftsIndex({ shifts, employees }: Props) {
    const [editing, setEditing] = useState<Shift | null>(null);
    const [open, setOpen] = useState(false);
    const [assigning, setAssigning] = useState<Shift | null>(null);

    return (
        <PageBody>
            <Head title="Shifts" />

            <PageHeader
                title="Shifts"
                description="Work hours, grace period and overtime rules. Employees without a shift use the default."
                actions={
                    <Button
                        onClick={() => {
                            setEditing(null);
                            setOpen(true);
                        }}
                    >
                        <Plus /> New shift
                    </Button>
                }
            />

            {shifts.length === 0 ? (
                <EmptyState
                    icon="clock"
                    title="No shifts yet"
                    description="A built-in 09:00–18:00 shift is used until you add one."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Shift</TableHead>
                                <TableHead>Hours</TableHead>
                                <TableHead>Grace</TableHead>
                                <TableHead>Half day under</TableHead>
                                <TableHead>Overtime after</TableHead>
                                <TableHead>Assigned</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-32" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {shifts.map((s) => (
                                <TableRow key={s.id}>
                                    <TableCell className="font-medium">
                                        {s.name}
                                        {s.is_default && (
                                            <span className="ml-2">
                                                <StatusBadge
                                                    label="Default"
                                                    color="blue"
                                                />
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {s.start_time} – {s.end_time}
                                    </TableCell>
                                    <TableCell>{s.grace_minutes} min</TableCell>
                                    <TableCell>
                                        {s.half_day_minutes} min
                                    </TableCell>
                                    <TableCell>
                                        {s.overtime_after_minutes} min
                                    </TableCell>
                                    <TableCell>{s.assigned}</TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={
                                                s.is_active
                                                    ? 'Active'
                                                    : 'Inactive'
                                            }
                                            color={
                                                s.is_active ? 'green' : 'gray'
                                            }
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Assign employees to ${s.name}`}
                                                onClick={() => setAssigning(s)}
                                            >
                                                <Users />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${s.name}`}
                                                onClick={() => {
                                                    setEditing(s);
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
                                                        aria-label={`Delete ${s.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title={`Delete ${s.name}?`}
                                                action={ShiftController.destroy(
                                                    s.id,
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
                            {editing ? `Edit ${editing.name}` : 'New shift'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? ShiftController.update.form(editing.id)
                            : ShiftController.store.form())}
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
                                <div className="grid grid-cols-2 gap-4">
                                    <FormField
                                        label="Start"
                                        htmlFor="start_time"
                                        error={errors.start_time}
                                        required
                                    >
                                        <Input
                                            id="start_time"
                                            name="start_time"
                                            type="time"
                                            defaultValue={
                                                editing?.start_time ?? '09:00'
                                            }
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="End"
                                        htmlFor="end_time"
                                        error={errors.end_time}
                                        required
                                    >
                                        <Input
                                            id="end_time"
                                            name="end_time"
                                            type="time"
                                            defaultValue={
                                                editing?.end_time ?? '18:00'
                                            }
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="Grace (min)"
                                        htmlFor="grace_minutes"
                                        error={errors.grace_minutes}
                                        required
                                    >
                                        <Input
                                            id="grace_minutes"
                                            name="grace_minutes"
                                            type="number"
                                            min="0"
                                            defaultValue={
                                                editing?.grace_minutes ?? 15
                                            }
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="Half day under (min)"
                                        htmlFor="half_day_minutes"
                                        error={errors.half_day_minutes}
                                        required
                                    >
                                        <Input
                                            id="half_day_minutes"
                                            name="half_day_minutes"
                                            type="number"
                                            min="0"
                                            defaultValue={
                                                editing?.half_day_minutes ?? 240
                                            }
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="Overtime after (min)"
                                        htmlFor="overtime_after_minutes"
                                        error={errors.overtime_after_minutes}
                                        hint="Extra time past the shift before overtime counts."
                                        required
                                    >
                                        <Input
                                            id="overtime_after_minutes"
                                            name="overtime_after_minutes"
                                            type="number"
                                            min="0"
                                            defaultValue={
                                                editing?.overtime_after_minutes ??
                                                30
                                            }
                                            required
                                        />
                                    </FormField>
                                </div>
                                <div className="flex flex-wrap gap-4">
                                    <Toggle
                                        name="is_default"
                                        label="Default shift"
                                        checked={editing?.is_default ?? false}
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
                                            : 'Create shift'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={assigning !== null}
                onOpenChange={(o) => !o && setAssigning(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    {assigning && (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Assign employees to {assigning.name}
                                </DialogTitle>
                            </DialogHeader>
                            <Form
                                key={assigning.id}
                                {...ShiftController.assign.form(assigning.id)}
                                options={{ preserveScroll: true }}
                                onSuccess={() => setAssigning(null)}
                                className="grid gap-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid max-h-72 gap-2 overflow-y-auto rounded-md border p-3">
                                            {employees.map((e) => (
                                                <div
                                                    key={e.value}
                                                    className="flex items-center gap-2"
                                                >
                                                    <Checkbox
                                                        id={`emp-${e.value}`}
                                                        name="employee_ids[]"
                                                        value={String(e.value)}
                                                    />
                                                    <Label
                                                        htmlFor={`emp-${e.value}`}
                                                    >
                                                        {e.label}
                                                    </Label>
                                                </div>
                                            ))}
                                        </div>
                                        {errors.employee_ids && (
                                            <p className="text-sm text-destructive">
                                                {errors.employee_ids}
                                            </p>
                                        )}
                                        <DialogFooter>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Assign
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

ShiftsIndex.layout = {
    breadcrumbs: [{ title: 'Shifts', href: index() }],
};
