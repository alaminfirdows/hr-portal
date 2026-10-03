import { Form, Head } from '@inertiajs/react';
import { Download, ExternalLink, Pencil, Share2, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DocumentController from '@/actions/Modules/Documents/Http/Controllers/DocumentController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { FormField } from '@/components/portal/form-field';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { StatusBadge } from '@/components/portal/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatDate } from '@/lib/format';
import { destroy, download, index } from '@/routes/documents';
import type { Option } from '@/types';
import { DocumentFields } from '../../components/document-fields';
import type {
    DocumentDefaults,
    DocumentOptions,
    FolderRow,
} from '../../components/document-fields';
import { OptionChecklist } from '../../components/option-checklist';

type DocumentDetail = {
    id: number;
    title: string;
    description: string | null;
    category: string | null;
    tags: string[];
    kind: 'file' | 'link';
    visibility: string;
    visibility_label: string;
    visibility_color: string;
    folder_id: number | null;
    folder: string | null;
    file_name: string | null;
    mime_type: string | null;
    size: number | null;
    url: string | null;
    expires_on: string | null;
    expires_in_days: number | null;
    uploader: string | null;
    employee: string | null;
    employee_id: number | null;
    department: string | null;
    department_id: number | null;
    team: string | null;
    team_id: number | null;
    project_id: number | null;
    shared_with: Option<number>[];
    created_at: string;
    can: { update: boolean; delete: boolean; share: boolean };
};

type Props = {
    document: DocumentDetail;
    options: DocumentOptions;
    folders: FolderRow[];
};

function Detail({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm">{value ?? '—'}</dd>
        </div>
    );
}

export default function DocumentShow({
    document: doc,
    options,
    folders,
}: Props) {
    const [dialog, setDialog] = useState<'edit' | 'share' | null>(null);
    const defaults: DocumentDefaults = {
        title: doc.title,
        description: doc.description,
        folder_id: doc.folder_id,
        category: doc.category,
        tags: doc.tags,
        visibility: doc.visibility,
        department_id: doc.department_id,
        team_id: doc.team_id,
        project_id: doc.project_id,
        employee_id: doc.employee_id,
        expires_on: doc.expires_on,
        url: doc.url,
        shared_with: doc.shared_with,
    };

    return (
        <PageBody>
            <Head title={doc.title} />

            <PageHeader
                title={doc.title}
                description={doc.description}
                actions={
                    <>
                        {doc.kind === 'file' ? (
                            <Button asChild>
                                <a href={download(doc.id).url}>
                                    <Download /> Download
                                </a>
                            </Button>
                        ) : (
                            doc.url && (
                                <Button asChild>
                                    <a
                                        href={doc.url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <ExternalLink /> Open link
                                    </a>
                                </Button>
                            )
                        )}
                        {doc.can.share && (
                            <Button
                                variant="outline"
                                onClick={() => setDialog('share')}
                            >
                                <Share2 /> Share
                            </Button>
                        )}
                        {doc.can.update && (
                            <Button
                                variant="outline"
                                onClick={() => setDialog('edit')}
                            >
                                <Pencil /> Edit
                            </Button>
                        )}
                        {doc.can.delete && (
                            <ConfirmAction
                                trigger={
                                    <Button variant="outline">
                                        <Trash2 /> Delete
                                    </Button>
                                }
                                title="Delete this document?"
                                description="The document and its file are removed for everyone."
                                action={destroy(doc.id)}
                                confirmLabel="Delete"
                                destructive
                            />
                        )}
                    </>
                }
            />

            <Card>
                <CardContent className="space-y-4">
                    <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt className="text-xs text-muted-foreground">
                                Visible to
                            </dt>
                            <dd className="pt-1">
                                <StatusBadge
                                    label={doc.visibility_label}
                                    color={doc.visibility_color}
                                />
                            </dd>
                        </div>
                        <Detail label="Folder" value={doc.folder} />
                        <Detail label="Category" value={doc.category} />
                        <Detail label="Uploaded by" value={doc.uploader} />
                        <Detail
                            label="Added"
                            value={formatDate(doc.created_at)}
                        />
                        <Detail
                            label="Expires"
                            value={
                                doc.expires_on
                                    ? formatDate(doc.expires_on)
                                    : null
                            }
                        />
                        <Detail label="Department" value={doc.department} />
                        <Detail label="Team" value={doc.team} />
                        <Detail label="Employee" value={doc.employee} />
                        {doc.kind === 'file' && (
                            <Detail label="File" value={doc.file_name} />
                        )}
                        {doc.kind === 'link' && (
                            <Detail label="Link" value={doc.url} />
                        )}
                    </dl>
                    {doc.tags.length > 0 && (
                        <div className="flex flex-wrap gap-1">
                            {doc.tags.map((tag) => (
                                <Badge key={tag} variant="outline">
                                    {tag}
                                </Badge>
                            ))}
                        </div>
                    )}
                    {doc.shared_with.length > 0 && (
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Shared with
                            </p>
                            <p className="text-sm">
                                {doc.shared_with.map((u) => u.label).join(', ')}
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Dialog
                open={dialog === 'edit'}
                onOpenChange={(open) => !open && setDialog(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Edit document</DialogTitle>
                    </DialogHeader>
                    <Form
                        {...DocumentController.update.form(doc.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setDialog(null)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <DocumentFields
                                    errors={errors}
                                    options={options}
                                    folders={folders}
                                    defaults={defaults}
                                    withUrl={doc.kind === 'link'}
                                />
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Save
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={dialog === 'share'}
                onOpenChange={(open) => !open && setDialog(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Share document</DialogTitle>
                    </DialogHeader>
                    <Form
                        {...DocumentController.share.form(doc.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setDialog(null)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="People"
                                    error={errors.user_ids}
                                >
                                    <OptionChecklist
                                        name="user_ids"
                                        options={options.users}
                                        initial={doc.shared_with.map(
                                            (u) => u.value,
                                        )}
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Save sharing
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

DocumentShow.layout = {
    breadcrumbs: [{ title: 'Documents', href: index() }],
};
