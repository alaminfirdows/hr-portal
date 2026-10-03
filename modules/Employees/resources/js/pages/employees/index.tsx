import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    FilterSelect,
    SearchInput,
} from '@/components/portal/filters';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
import { StatusBadge } from '@/components/portal/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { create, index, show } from '@/routes/employees';
import type { Option, Paginated } from '@/types';
import { EmployeeAvatar } from '../../components/employee-avatar';
import { STATUS_COLORS } from '../../components/status';

type EmployeeSummary = {
    id: number;
    employee_code: string | null;
    full_name: string;
    email: string;
    photo_url: string | null;
    department: string | null;
    team: string | null;
    position: string | null;
    status: string;
    status_label: string;
};

type Filters = {
    search?: string;
    status?: string;
    department_id?: string;
    team_id?: string;
    employment_type?: string;
};

type Props = {
    employees: Paginated<EmployeeSummary>;
    filters: Filters;
    options: {
        statuses: Option<string>[];
        employment_types: Option<string>[];
        departments: Option<number>[];
        teams: Option<number>[];
    };
    can: { create: boolean };
};

export default function EmployeesIndex({
    employees,
    filters,
    options,
    can,
}: Props) {
    const patch = (next: Record<string, string | number | null>) =>
        applyFilters({ ...filters }, next);
    const active = Object.values(filters).some(
        (v) => v !== undefined && v !== '',
    );

    return (
        <PageBody>
            <Head title="Employees" />

            <PageHeader
                title="Employees"
                description="Everyone you can manage."
                actions={
                    can.create && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus /> New employee
                            </Link>
                        </Button>
                    )
                }
            />

            <FilterBar active={active} onReset={() => applyFilters({}, {})}>
                <SearchInput
                    value={filters.search}
                    onSearch={(v) => patch({ search: v })}
                    placeholder="Search employees…"
                />
                <FilterSelect
                    value={
                        filters.department_id
                            ? Number(filters.department_id)
                            : null
                    }
                    options={options.departments}
                    placeholder="Departments"
                    onChange={(v) => patch({ department_id: v })}
                />
                <FilterSelect
                    value={filters.team_id ? Number(filters.team_id) : null}
                    options={options.teams}
                    placeholder="Teams"
                    onChange={(v) => patch({ team_id: v })}
                />
                <FilterSelect
                    value={filters.status ?? null}
                    options={options.statuses}
                    placeholder="Statuses"
                    onChange={(v) => patch({ status: v })}
                />
                <FilterSelect
                    value={filters.employment_type ?? null}
                    options={options.employment_types}
                    placeholder="Employment types"
                    onChange={(v) => patch({ employment_type: v })}
                />
            </FilterBar>

            {employees.data.length === 0 ? (
                <EmptyState
                    icon="users"
                    title="No employees found"
                    description="Try changing the search or filters."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Code</TableHead>
                                <TableHead>Position</TableHead>
                                <TableHead>Department</TableHead>
                                <TableHead>Team</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {employees.data.map((e) => (
                                <TableRow key={e.id}>
                                    <TableCell>
                                        <Link
                                            href={show(e.id)}
                                            className="flex items-center gap-3 hover:underline"
                                        >
                                            <EmployeeAvatar
                                                name={e.full_name}
                                                url={e.photo_url}
                                            />
                                            <span>
                                                <span className="block font-medium">
                                                    {e.full_name}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {e.email}
                                                </span>
                                            </span>
                                        </Link>
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {e.employee_code ?? '—'}
                                    </TableCell>
                                    <TableCell>{e.position ?? '—'}</TableCell>
                                    <TableCell>{e.department ?? '—'}</TableCell>
                                    <TableCell>{e.team ?? '—'}</TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            label={e.status_label}
                                            color={STATUS_COLORS[e.status]}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PaginationLinks paginator={employees} />
        </PageBody>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [{ title: 'Employees', href: index() }],
};
