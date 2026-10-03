import { Head, Link } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    FilterSelect,
    SearchInput,
} from '@/components/portal/filters';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
import { Card, CardContent } from '@/components/ui/card';
import { directory } from '@/routes/employees';
import { show } from '@/routes/employees/directory';
import type { Option, Paginated } from '@/types';
import { EmployeeAvatar } from '../../../components/employee-avatar';

type Colleague = {
    id: number;
    full_name: string;
    email: string;
    photo_url: string | null;
    department: string | null;
    team: string | null;
    position: string | null;
};

type Filters = {
    search?: string;
    department_id?: string;
    team_id?: string;
    location_id?: string;
};

type Props = {
    employees: Paginated<Colleague>;
    filters: Filters;
    options: {
        departments: Option<number>[];
        teams: Option<number>[];
        locations: Option<number>[];
    };
};

export default function DirectoryIndex({ employees, filters, options }: Props) {
    const patch = (next: Record<string, string | number | null>) =>
        applyFilters({ ...filters }, next);
    const active = Object.values(filters).some(
        (v) => v !== undefined && v !== '',
    );

    return (
        <PageBody>
            <Head title="Directory" />
            <PageHeader
                title="Directory"
                description="Find colleagues across the company."
            />

            <FilterBar active={active} onReset={() => applyFilters({}, {})}>
                <SearchInput
                    value={filters.search}
                    onSearch={(v) => patch({ search: v })}
                    placeholder="Search people…"
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
            </FilterBar>

            {employees.data.length === 0 ? (
                <EmptyState
                    icon="users"
                    title="No colleagues found"
                    description="Try a different search."
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    {employees.data.map((e) => (
                        <Link
                            key={e.id}
                            href={show(e.id)}
                            className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <Card className="h-full transition-colors hover:bg-muted/50">
                                <CardContent className="flex flex-col items-center gap-2 text-center">
                                    <EmployeeAvatar
                                        name={e.full_name}
                                        url={e.photo_url}
                                        className="size-16 text-lg"
                                    />
                                    <div>
                                        <p className="font-medium">
                                            {e.full_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {e.position ?? '—'}
                                        </p>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {[e.department, e.team]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </p>
                                    <p className="flex max-w-full items-center gap-1 text-xs text-muted-foreground">
                                        <Mail className="size-3 shrink-0" />
                                        <span className="truncate">
                                            {e.email}
                                        </span>
                                    </p>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>
            )}

            <PaginationLinks paginator={employees} />
        </PageBody>
    );
}

DirectoryIndex.layout = {
    breadcrumbs: [{ title: 'Directory', href: directory() }],
};
