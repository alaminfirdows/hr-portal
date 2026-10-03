import { Head } from '@inertiajs/react';
import { ScrollText } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    FilterSelect,
    SearchInput,
} from '@/components/portal/filters';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
import { formatDateTime } from '@/lib/format';
import { index } from '@/routes/audit';
import type { Option, Paginated } from '@/types';

type Change = { from: unknown; to: unknown };

type AuditLog = {
    id: number;
    action: string;
    description: string | null;
    actor: string | null;
    subject: string | null;
    changes: Record<string, Change>;
    ip_address: string | null;
    created_at: string | null;
};

type Filters = {
    search?: string;
    action?: string;
    actor_id?: string;
    from?: string;
    to?: string;
};

type Props = {
    logs: Paginated<AuditLog>;
    filters: Filters;
    modules: string[];
    actors: Option<number>[];
};

function show(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }

    return typeof value === 'string' ? value : JSON.stringify(value);
}

export default function AuditIndex({ logs, filters, modules, actors }: Props) {
    const [selected, setSelected] = useState<AuditLog | null>(null);
    const current: Record<string, string | undefined> = { ...filters };
    const patch = (p: Record<string, string | number | null>) =>
        applyFilters(current, p);
    const active = Object.values(filters).some((v) => v);
    const changeEntries = selected ? Object.entries(selected.changes) : [];

    return (
        <PageBody>
            <Head title="Audit log" />
            <PageHeader
                title="Audit log"
                description="Who did what, and when."
            />

            <FilterBar active={active} onReset={() => applyFilters({}, {})}>
                <SearchInput
                    value={filters.search}
                    onSearch={(v) => patch({ search: v })}
                    placeholder="Search description"
                />
                <FilterSelect
                    value={filters.action ?? null}
                    placeholder="Module"
                    options={modules.map((m) => ({ value: m, label: m }))}
                    onChange={(v) => patch({ action: v })}
                />
                <FilterSelect
                    value={filters.actor_id ? Number(filters.actor_id) : null}
                    placeholder="Actor"
                    options={actors}
                    onChange={(v) => patch({ actor_id: v })}
                />
                <Input
                    type="date"
                    aria-label="From date"
                    className="w-full sm:w-40"
                    value={filters.from ?? ''}
                    onChange={(e) => patch({ from: e.target.value })}
                />
                <Input
                    type="date"
                    aria-label="To date"
                    className="w-full sm:w-40"
                    value={filters.to ?? ''}
                    onChange={(e) => patch({ to: e.target.value })}
                />
            </FilterBar>

            {logs.data.length === 0 ? (
                <EmptyState icon="scroll-text" title="No audit entries found" />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>When</TableHead>
                                <TableHead>Actor</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Subject</TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {logs.data.map((log) => (
                                <TableRow key={log.id}>
                                    <TableCell className="whitespace-nowrap">
                                        {formatDateTime(log.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        {log.actor ?? 'System'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {log.action}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="max-w-xs truncate">
                                        {log.description}
                                    </TableCell>
                                    <TableCell>{log.subject ?? '—'}</TableCell>
                                    <TableCell className="text-right">
                                        {Object.keys(log.changes).length >
                                            0 && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setSelected(log)}
                                            >
                                                <ScrollText className="size-4" />{' '}
                                                Details
                                            </Button>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PaginationLinks paginator={logs} />

            <Dialog
                open={selected !== null}
                onOpenChange={(o) => !o && setSelected(null)}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{selected?.action}</DialogTitle>
                        <DialogDescription>
                            {selected?.description}
                            {selected?.ip_address
                                ? ` · ${selected.ip_address}`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-96 overflow-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Field</TableHead>
                                    <TableHead>Before</TableHead>
                                    <TableHead>After</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {changeEntries.map(([key, change]) => (
                                    <TableRow key={key}>
                                        <TableCell className="font-medium">
                                            {key}
                                        </TableCell>
                                        <TableCell className="break-all text-red-600 dark:text-red-400">
                                            {show(change.from)}
                                        </TableCell>
                                        <TableCell className="break-all text-green-600 dark:text-green-400">
                                            {show(change.to)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}

AuditIndex.layout = {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
};
