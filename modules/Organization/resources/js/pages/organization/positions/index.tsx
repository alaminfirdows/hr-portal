import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PositionController from '@/actions/Modules/Organization/Http/Controllers/PositionController';
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
import { OptionSelect } from '@/components/portal/option-select';
import { index } from '@/routes/organization/positions';
import type { Option } from '@/types';

type Position = {
    id: number;
    title: string;
    level: string | null;
    department_id: number | null;
    department: string | null;
    employees_count: number;
};

type Props = {
    positions: Position[];
    departments: Option<number>[];
};

export default function PositionsIndex({ positions, departments }: Props) {
    const [editing, setEditing] = useState<Position | null>(null);
    const [open, setOpen] = useState(false);

    const startCreate = () => {
        setEditing(null);
        setOpen(true);
    };

    const startEdit = (position: Position) => {
        setEditing(position);
        setOpen(true);
    };

    return (
        <PageBody>
            <Head title="Positions" />

            <PageHeader
                title="Positions"
                description="Job titles employees can hold."
                actions={
                    <Button onClick={startCreate}>
                        <Plus /> New position
                    </Button>
                }
            />

            {positions.length === 0 ? (
                <EmptyState
                    icon="briefcase"
                    title="No positions yet"
                    description="Create the first position."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Title</TableHead>
                                <TableHead>Level</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead className="text-right">
                                    Employees
                                </TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {positions.map((position) => (
                                <TableRow key={position.id}>
                                    <TableCell className="font-medium">
                                        {position.title}
                                    </TableCell>
                                    <TableCell>
                                        {position.level ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {position.department ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {position.employees_count}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${position.title}`}
                                                onClick={() =>
                                                    startEdit(position)
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${position.title}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title={`Delete ${position.title}?`}
                                                description="Positions held by employees cannot be deleted."
                                                action={PositionController.destroy(
                                                    position.id,
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
                            {editing ? `Edit ${editing.title}` : 'New position'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? PositionController.update.form(editing.id)
                            : PositionController.store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Title"
                                    htmlFor="title"
                                    error={errors.title}
                                    required
                                >
                                    <Input
                                        id="title"
                                        name="title"
                                        defaultValue={editing?.title}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Level"
                                    htmlFor="level"
                                    error={errors.level}
                                    hint="e.g. Junior, Senior, Lead."
                                >
                                    <Input
                                        id="level"
                                        name="level"
                                        defaultValue={editing?.level ?? ''}
                                    />
                                </FormField>
                                <FormField
                                    label="Department"
                                    htmlFor="department_id"
                                    error={errors.department_id}
                                >
                                    <OptionSelect
                                        id="department_id"
                                        name="department_id"
                                        value={editing?.department_id ?? null}
                                        options={departments}
                                        allowEmpty
                                        emptyLabel="Any department"
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing
                                            ? 'Save changes'
                                            : 'Create position'}
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

PositionsIndex.layout = {
    breadcrumbs: [{ title: 'Positions', href: index() }],
};
