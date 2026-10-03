import { Head, Link } from '@inertiajs/react';
import { Lock, Plus, Settings2 } from 'lucide-react';
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
import { Card, CardContent } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import { formatDate } from '@/lib/format';
import { create, index, show } from '@/routes/projects';
import { index as fieldsIndex } from '@/routes/projects/fields';
import type { Option, Paginated } from '@/types';
import type { ProjectCard } from '../../components/types';

type Filters = {
    search?: string;
    status?: string;
    visibility?: string;
    mine?: string;
};

type Props = {
    projects: Paginated<ProjectCard>;
    filters: Filters;
    options: { statuses: Option<string>[]; visibilities: Option<string>[] };
    can: { create: boolean; fields: boolean };
};

function ProjectTile({ project }: { project: ProjectCard }) {
    const body = (
        <Card
            className={
                project.can_open
                    ? 'h-full transition-shadow hover:shadow-md'
                    : 'h-full opacity-80'
            }
        >
            <CardContent className="flex h-full flex-col gap-3 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="truncate font-medium">{project.name}</p>
                        <p className="font-mono text-xs text-muted-foreground">
                            {project.code}
                        </p>
                    </div>
                    <StatusBadge
                        label={project.status_label}
                        color={project.status_color}
                    />
                </div>
                {project.can_open ? (
                    <>
                        {project.description && (
                            <p className="line-clamp-2 text-sm text-muted-foreground">
                                {project.description}
                            </p>
                        )}
                        <div className="mt-auto flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            {project.priority_label && (
                                <StatusBadge
                                    label={project.priority_label}
                                    color={project.priority_color}
                                />
                            )}
                            {project.visibility !== 'public' && (
                                <StatusBadge
                                    label={project.visibility_label}
                                    color={project.visibility_color}
                                />
                            )}
                            <span>{project.manager ?? 'No manager'}</span>
                            <span>{project.members_count ?? 0} members</span>
                            {project.end_date && (
                                <span>Due {formatDate(project.end_date)}</span>
                            )}
                        </div>
                    </>
                ) : (
                    <p className="mt-auto flex items-center gap-2 text-sm text-muted-foreground">
                        <Lock className="size-4" />
                        Restricted to members
                    </p>
                )}
            </CardContent>
        </Card>
    );

    return project.can_open ? (
        <Link href={show(project.id)} className="block">
            {body}
        </Link>
    ) : (
        <div aria-disabled="true">{body}</div>
    );
}

export default function ProjectsIndex({
    projects,
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
            <Head title="Projects" />

            <PageHeader
                title="Projects"
                description="Browse projects and the people on them."
                actions={
                    <>
                        {can.fields && (
                            <Button variant="outline" asChild>
                                <Link href={fieldsIndex()}>
                                    <Settings2 /> Custom fields
                                </Link>
                            </Button>
                        )}
                        {can.create && (
                            <Button asChild>
                                <Link href={create()}>
                                    <Plus /> New project
                                </Link>
                            </Button>
                        )}
                    </>
                }
            />

            <FilterBar active={active} onReset={() => applyFilters({}, {})}>
                <SearchInput
                    value={filters.search}
                    onSearch={(v) => patch({ search: v })}
                    placeholder="Search projects…"
                />
                <FilterSelect
                    value={filters.status ?? null}
                    options={options.statuses}
                    placeholder="Statuses"
                    onChange={(v) => patch({ status: v })}
                />
                <FilterSelect
                    value={filters.visibility ?? null}
                    options={options.visibilities}
                    placeholder="Visibility"
                    onChange={(v) => patch({ visibility: v })}
                />
                <label className="flex items-center gap-2 text-sm">
                    <Switch
                        checked={Boolean(filters.mine)}
                        onCheckedChange={(checked) =>
                            patch({ mine: checked ? 1 : null })
                        }
                    />
                    My projects
                </label>
            </FilterBar>

            {projects.data.length === 0 ? (
                <EmptyState
                    icon="folder-kanban"
                    title="No projects found"
                    description="Try changing the search or filters."
                />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {projects.data.map((p) => (
                        <ProjectTile key={p.id} project={p} />
                    ))}
                </div>
            )}

            <PaginationLinks paginator={projects} />
        </PageBody>
    );
}

ProjectsIndex.layout = {
    breadcrumbs: [{ title: 'Projects', href: index() }],
};
