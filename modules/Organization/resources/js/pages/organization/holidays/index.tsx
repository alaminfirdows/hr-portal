import { Form, Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import HolidayController from '@/actions/Modules/Organization/Http/Controllers/HolidayController';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { StatusBadge } from '@/components/portal/status-badge';
import { formatDate } from '@/lib/format';
import { index } from '@/routes/organization/holidays';

type Holiday = {
    id: number;
    name: string;
    date: string;
    weekday: string;
    is_optional: boolean;
    is_past: boolean;
};

type Props = {
    holidays: Holiday[];
    year: number;
    canManage: boolean;
};

export default function HolidaysIndex({ holidays, year, canManage }: Props) {
    const [editing, setEditing] = useState<Holiday | null>(null);
    const [open, setOpen] = useState(false);

    const goToYear = (target: number) => {
        router.visit(index({ query: { year: target } }).url, {
            preserveScroll: true,
        });
    };

    const startCreate = () => {
        setEditing(null);
        setOpen(true);
    };

    const startEdit = (holiday: Holiday) => {
        setEditing(holiday);
        setOpen(true);
    };

    return (
        <PageBody>
            <Head title="Holidays" />

            <PageHeader
                title="Holidays"
                description="Company holiday calendar."
                actions={
                    <>
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
                        {canManage && (
                            <Button onClick={startCreate}>
                                <Plus /> New holiday
                            </Button>
                        )}
                    </>
                }
            />

            {holidays.length === 0 ? (
                <EmptyState
                    icon="calendar-days"
                    title={`No holidays in ${year}`}
                    description={
                        canManage ? 'Add holidays to the calendar.' : undefined
                    }
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Holiday</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Day</TableHead>
                                <TableHead>Type</TableHead>
                                {canManage && <TableHead className="w-24" />}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {holidays.map((holiday) => (
                                <TableRow
                                    key={holiday.id}
                                    className={
                                        holiday.is_past
                                            ? 'text-muted-foreground'
                                            : undefined
                                    }
                                >
                                    <TableCell className="font-medium">
                                        {holiday.name}
                                    </TableCell>
                                    <TableCell>
                                        {formatDate(holiday.date)}
                                    </TableCell>
                                    <TableCell>{holiday.weekday}</TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={
                                                holiday.is_optional
                                                    ? 'Optional'
                                                    : 'Public'
                                            }
                                            color={
                                                holiday.is_optional
                                                    ? 'amber'
                                                    : 'green'
                                            }
                                        />
                                    </TableCell>
                                    {canManage && (
                                        <TableCell>
                                            <div className="flex justify-end">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Edit ${holiday.name}`}
                                                    onClick={() =>
                                                        startEdit(holiday)
                                                    }
                                                >
                                                    <Pencil />
                                                </Button>
                                                <ConfirmAction
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Delete ${holiday.name}`}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    }
                                                    title={`Delete ${holiday.name}?`}
                                                    action={HolidayController.destroy(
                                                        holiday.id,
                                                    )}
                                                    confirmLabel="Delete"
                                                    destructive
                                                />
                                            </div>
                                        </TableCell>
                                    )}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            {canManage && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {editing
                                    ? `Edit ${editing.name}`
                                    : 'New holiday'}
                            </DialogTitle>
                        </DialogHeader>
                        <Form
                            key={editing?.id ?? 'new'}
                            {...(editing
                                ? HolidayController.update.form(editing.id)
                                : HolidayController.store.form())}
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
                                        label="Date"
                                        htmlFor="date"
                                        error={errors.date}
                                        required
                                    >
                                        <Input
                                            id="date"
                                            name="date"
                                            type="date"
                                            defaultValue={editing?.date}
                                            required
                                        />
                                    </FormField>
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="hidden"
                                            name="is_optional"
                                            value="0"
                                        />
                                        <Checkbox
                                            id="is_optional"
                                            name="is_optional"
                                            value="1"
                                            defaultChecked={
                                                editing?.is_optional ?? false
                                            }
                                        />
                                        <Label htmlFor="is_optional">
                                            Optional holiday
                                        </Label>
                                    </div>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {editing
                                                ? 'Save changes'
                                                : 'Create holiday'}
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            )}
        </PageBody>
    );
}

HolidaysIndex.layout = {
    breadcrumbs: [{ title: 'Holidays', href: index() }],
};
