import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DepartmentController from '@/actions/Modules/Organization/Http/Controllers/DepartmentController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
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
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/organization/departments';
import type { Option } from '@/types';

type Department = {
    id: number;
    name: string;
    code: string;
    description: string | null;
    head_employee_id: number | null;
    head: string | null;
    teams_count: number;
    employees_count: number;
};

type Props = {
    departments: Department[];
    employees: Option<number>[];
};

export default function DepartmentsIndex({ departments, employees }: Props) {
    const [editing, setEditing] = useState<Department | null>(null);
    const [open, setOpen] = useState(false);

    const startCreate = () => {
        setEditing(null);
        setOpen(true);
    };

    const startEdit = (department: Department) => {
        setEditing(department);
        setOpen(true);
    };

    return (
        <PageBody>
            <Head title="Departments" />

            <PageHeader
                title="Departments"
                description="Major organizational units. Teams sit inside a department."
                actions={
                    <Button onClick={startCreate}>
                        <Plus /> New department
                    </Button>
                }
            />

            {departments.length === 0 ? (
                <EmptyState
                    icon="building-2"
                    title="No departments yet"
                    description="Create the first department to start organizing teams."
                />
            ) : (
                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Code</TableHead>
                                <TableHead>Head</TableHead>
                                <TableHead className="text-right">
                                    Teams
                                </TableHead>
                                <TableHead className="text-right">
                                    Employees
                                </TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {departments.map((department) => (
                                <TableRow key={department.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {department.name}
                                        </div>
                                        {department.description && (
                                            <div className="line-clamp-1 text-xs text-muted-foreground">
                                                {department.description}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {department.code}
                                    </TableCell>
                                    <TableCell>
                                        {department.head ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {department.teams_count}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {department.employees_count}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${department.name}`}
                                                onClick={() =>
                                                    startEdit(department)
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${department.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title={`Delete ${department.name}?`}
                                                description="Departments with teams or employees cannot be deleted."
                                                action={DepartmentController.destroy(
                                                    department.id,
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
                            {editing
                                ? `Edit ${editing.name}`
                                : 'New department'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? DepartmentController.update.form(editing.id)
                            : DepartmentController.store.form())}
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
                                    hint="Short unique code, e.g. ENG."
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
                                    label="Head"
                                    htmlFor="head_employee_id"
                                    error={errors.head_employee_id}
                                >
                                    <OptionSelect
                                        id="head_employee_id"
                                        name="head_employee_id"
                                        value={
                                            editing?.head_employee_id ?? null
                                        }
                                        options={employees}
                                        allowEmpty
                                        emptyLabel="No head"
                                    />
                                </FormField>
                                <FormField
                                    label="Description"
                                    htmlFor="description"
                                    error={errors.description}
                                >
                                    <Textarea
                                        id="description"
                                        name="description"
                                        defaultValue={
                                            editing?.description ?? ''
                                        }
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing
                                            ? 'Save changes'
                                            : 'Create department'}
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

DepartmentsIndex.layout = {
    breadcrumbs: [{ title: 'Departments', href: index() }],
};
