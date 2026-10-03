import { Form, Head, Link } from '@inertiajs/react';
import { ExternalLink, FolderPlus, Link2, Upload } from 'lucide-react';
import { useState } from 'react';
import DocumentController from '@/actions/Modules/Documents/Http/Controllers/DocumentController';
import FolderController from '@/actions/Modules/Documents/Http/Controllers/FolderController';
import { EmptyState } from '@/components/portal/empty-state';
import {
    applyFilters,
    FilterBar,
    FilterSelect,
    SearchInput,
} from '@/components/portal/filters';
import { FormField } from '@/components/portal/form-field';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { PaginationLinks } from '@/components/portal/pagination-links';
import { StatusBadge } from '@/components/portal/status-badge';
import { Badge } from '@/components/ui/badge';
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
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/documents';
import type { Option, Paginated } from '@/types';
import { DocumentFields } from '../../components/document-fields';
import type {
    DocumentOptions,
    FolderRow,
} from '../../components/document-fields';

type DocumentSummary = {
    id: number;
    title: string;
    description: string | null;
    category: string | null;
    tags: string[];
    kind: 'file' | 'link';
    visibility_label: string;
    visibility_color: string;
    folder: string | null;
    url: string | null;
    size: number | null;
    expires_on: string | null;
    expires_in_days: number | null;
    uploader: string | null;
    created_at: string;
};

type Filters = {
    search?: string;
    folder_id?: string;
    category?: string;
    tag?: string;
    visibility?: string;
};

type Props = {
    documents: Paginated<DocumentSummary>;
    filters: Filters;
    folders: FolderRow[];
    options: DocumentOptions & { categories: Option<string>[] };
    can: { upload: boolean; manage: boolean };
};

function formatSize(bytes: number | null): string {
    if (bytes === null) {
        return '';
    }

    return bytes >= 1_048_576
        ? `${(bytes / 1_048_576).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export default function DocumentsIndex({
    documents,
    filters,
    folders,
    options,
    can,
}: Props) {
    const [dialog, setDialog] = useState<'file' | 'link' | 'folder' | null>(
        null,
    );
    const patch = (next: Record<string, string | number | null>) =>
        applyFilters({ ...filters }, next);
    const active = Object.values(filters).some(
        (v) => v !== undefined && v !== '',
    );
    const activeFolder = filters.folder_id ? Number(filters.folder_id) : null;

    return (
        <PageBody>
            <Head title="Documents" />

            <PageHeader
                title="Documents"
                description="Policies, contracts and shared files you have access to."
                actions={
                    <>
                        {can.manage && (
                            <Button
                                variant="outline"
                                onClick={() => setDialog('folder')}
                            >
                                <FolderPlus /> New folder
                            </Button>
                        )}
                        {can.upload && (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => setDialog('link')}
                                >
                                    <Link2 /> Add link
                                </Button>
                                <Button onClick={() => setDialog('file')}>
                                    <Upload /> Upload
                                </Button>
                            </>
                        )}
                    </>
                }
            />

            <div className="grid gap-6 lg:grid-cols-[14rem_1fr]">
                <nav aria-label="Folders" className="space-y-1">
                    <button
                        type="button"
                        onClick={() => patch({ folder_id: null })}
                        className={cn(
                            'w-full rounded-md px-3 py-2 text-left text-sm hover:bg-muted',
                            activeFolder === null && 'bg-muted font-medium',
                        )}
                    >
                        All documents
                    </button>
                    {folders
                        .filter((f) => f.parent_id === null)
                        .map((folder) => (
                            <FolderNode
                                key={folder.id}
                                folder={folder}
                                all={folders}
                                activeId={activeFolder}
                                depth={0}
                                onSelect={(id) => patch({ folder_id: id })}
                            />
                        ))}
                </nav>

                <div className="space-y-4">
                    <FilterBar
                        active={active}
                        onReset={() => applyFilters({}, {})}
                    >
                        <SearchInput
                            value={filters.search}
                            onSearch={(v) => patch({ search: v })}
                            placeholder="Search documents…"
                        />
                        <FilterSelect
                            value={filters.category ?? null}
                            options={options.categories}
                            placeholder="Categories"
                            onChange={(v) => patch({ category: v })}
                        />
                        <FilterSelect
                            value={filters.visibility ?? null}
                            options={options.visibilities}
                            placeholder="Visibility"
                            onChange={(v) => patch({ visibility: v })}
                        />
                        {filters.tag && (
                            <Badge variant="secondary">
                                Tag: {filters.tag}
                            </Badge>
                        )}
                    </FilterBar>

                    {documents.data.length === 0 ? (
                        <EmptyState
                            icon="folders"
                            title="No documents found"
                            description="Try another folder or change the filters."
                        />
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Visible to</TableHead>
                                        <TableHead>Expires</TableHead>
                                        <TableHead>Added</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {documents.data.map((doc) => (
                                        <TableRow key={doc.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <Link
                                                        href={show(doc.id)}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {doc.title}
                                                    </Link>
                                                    {doc.kind === 'link' &&
                                                        doc.url && (
                                                            <a
                                                                href={doc.url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                aria-label={`Open ${doc.title} in a new tab`}
                                                                className="text-muted-foreground hover:text-foreground"
                                                            >
                                                                <ExternalLink className="size-4" />
                                                            </a>
                                                        )}
                                                </div>
                                                <div className="flex flex-wrap items-center gap-1 pt-1">
                                                    {doc.kind === 'file' && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatSize(
                                                                doc.size,
                                                            )}
                                                        </span>
                                                    )}
                                                    {doc.tags.map((tag) => (
                                                        <button
                                                            key={tag}
                                                            type="button"
                                                            onClick={() =>
                                                                patch({ tag })
                                                            }
                                                        >
                                                            <Badge variant="outline">
                                                                {tag}
                                                            </Badge>
                                                        </button>
                                                    ))}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {doc.category ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    label={doc.visibility_label}
                                                    color={doc.visibility_color}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {doc.expires_on ? (
                                                    <span
                                                        className={cn(
                                                            doc.expires_in_days !==
                                                                null &&
                                                                doc.expires_in_days <
                                                                    0 &&
                                                                'text-destructive',
                                                        )}
                                                    >
                                                        {formatDate(
                                                            doc.expires_on,
                                                        )}
                                                    </span>
                                                ) : (
                                                    '—'
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    {formatDate(doc.created_at)}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {doc.uploader}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}

                    <PaginationLinks paginator={documents} />
                </div>
            </div>

            <Dialog
                open={dialog === 'file' || dialog === 'link'}
                onOpenChange={(open) => !open && setDialog(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {dialog === 'link' ? 'Add link' : 'Upload document'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={dialog ?? 'closed'}
                        {...(dialog === 'link'
                            ? DocumentController.storeLink.form()
                            : DocumentController.store.form())}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                {dialog === 'file' && (
                                    <FormField
                                        label="File"
                                        htmlFor="file"
                                        error={errors.file}
                                        hint="Up to 20 MB: PDF, Office files, CSV, TXT, PNG or JPG."
                                        required
                                    >
                                        <Input
                                            id="file"
                                            name="file"
                                            type="file"
                                            required
                                        />
                                    </FormField>
                                )}
                                <DocumentFields
                                    errors={errors}
                                    options={options}
                                    folders={folders}
                                    withUrl={dialog === 'link'}
                                />
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {dialog === 'link' ? 'Add' : 'Upload'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={dialog === 'folder'}
                onOpenChange={(open) => !open && setDialog(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New folder</DialogTitle>
                    </DialogHeader>
                    <Form
                        {...FolderController.store.form()}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setDialog(null)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Name"
                                    htmlFor="folder-name"
                                    error={errors.name}
                                    required
                                >
                                    <Input
                                        id="folder-name"
                                        name="name"
                                        required
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Create folder
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

function FolderNode({
    folder,
    all,
    activeId,
    depth,
    onSelect,
}: {
    folder: FolderRow;
    all: FolderRow[];
    activeId: number | null;
    depth: number;
    onSelect: (id: number) => void;
}) {
    const children = all.filter((f) => f.parent_id === folder.id);

    return (
        <div>
            <button
                type="button"
                onClick={() => onSelect(folder.id)}
                style={{ paddingLeft: `${0.75 + depth}rem` }}
                className={cn(
                    'w-full rounded-md py-2 pr-3 text-left text-sm hover:bg-muted',
                    activeId === folder.id && 'bg-muted font-medium',
                )}
            >
                {folder.name}
            </button>
            {children.map((child) => (
                <FolderNode
                    key={child.id}
                    folder={child}
                    all={all}
                    activeId={activeId}
                    depth={depth + 1}
                    onSelect={onSelect}
                />
            ))}
        </div>
    );
}

DocumentsIndex.layout = {
    breadcrumbs: [{ title: 'Documents', href: index() }],
};
