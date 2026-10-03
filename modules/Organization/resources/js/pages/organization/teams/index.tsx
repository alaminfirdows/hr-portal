import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import TeamController from '@/actions/Modules/Organization/Http/Controllers/TeamController';
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
import {
    applyFilters,
    FilterBar,
    FilterSelect,
} from '@/components/portal/filters';
import { OptionSelect } from '@/components/portal/option-select';
import { index } from '@/routes/organization/teams';
import type { Option } from '@/types';

type Team = {
    id: number;
    name: string;
    department_id: number;
    department: string;
    lead_employee_id: number | null;
    lead: string | null;
    employees_count: number;
};

type Props = {
    teams: Team[];
    departments: Option<number>[];
    employees: Option<number>[];
    filters: { department: number | null };
};

export default function TeamsIndex({
    teams,
    departments,
    employees,
    filters,
}: Props) {
    const [editing, setEditing] = useState<Team | null>(null);
    const [open, setOpen] = useState(false);

    const startCreate = () => {
        setEditing(null);
        setOpen(true);
    };

    const startEdit = (team: Team) => {
        setEditing(team);
        setOpen(true);
    };

    return (
        <PageBody>
            <Head title="Teams" />

            <PageHeader
                title="Teams"
                description="Working groups inside a department."
                actions={
                    <Button onClick={startCreate}>
                        <Plus /> New team
                    </Button>
                }
            />

            <FilterBar
                active={filters.department !== null}
                onReset={() => applyFilters({}, { department: null })}
            >
                <FilterSelect
                    value={filters.department}
                    options={departments}
                    placeholder="All departments"
                    onChange={(department) =>
                        applyFilters(filters, { department })
                    }
                />
            </FilterBar>

            {teams.length === 0 ? (
                <EmptyState
                    icon="users"
                    title="No teams found"
                    description="Create a team inside a department."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Lead</TableHead>
                                <TableHead className="text-right">
                                    Employees
                                </TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {teams.map((team) => (
                                <TableRow key={team.id}>
                                    <TableCell className="font-medium">
                                        {team.name}
                                    </TableCell>
                                    <TableCell>{team.department}</TableCell>
                                    <TableCell>{team.lead ?? '—'}</TableCell>
                                    <TableCell className="text-right">
                                        {team.employees_count}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${team.name}`}
                                                onClick={() => startEdit(team)}
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${team.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title={`Delete ${team.name}?`}
                                                description="Teams with employees cannot be deleted."
                                                action={TeamController.destroy(
                                                    team.id,
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
                            {editing ? `Edit ${editing.name}` : 'New team'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? TeamController.update.form(editing.id)
                            : TeamController.store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Department"
                                    htmlFor="department_id"
                                    error={errors.department_id}
                                    required
                                >
                                    <OptionSelect
                                        id="department_id"
                                        name="department_id"
                                        value={
                                            editing?.department_id ??
                                            filters.department
                                        }
                                        options={departments}
                                        placeholder="Select department"
                                    />
                                </FormField>
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
                                    label="Lead"
                                    htmlFor="lead_employee_id"
                                    error={errors.lead_employee_id}
                                >
                                    <OptionSelect
                                        id="lead_employee_id"
                                        name="lead_employee_id"
                                        value={
                                            editing?.lead_employee_id ?? null
                                        }
                                        options={employees}
                                        allowEmpty
                                        emptyLabel="No lead"
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing
                                            ? 'Save changes'
                                            : 'Create team'}
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

TeamsIndex.layout = {
    breadcrumbs: [{ title: 'Teams', href: index() }],
};
