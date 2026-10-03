import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import ProjectFieldController from '@/actions/Modules/Projects/Http/Controllers/ProjectFieldController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { index as projectsIndex } from '@/routes/projects';
import { index } from '@/routes/projects/fields';
import type { Option } from '@/types';

type Field = {
    id: number;
    key: string;
    label: string;
    type: string;
    type_label: string;
    options: string[];
    is_required: boolean;
    sort: number;
    values_count: number;
};

type Props = {
    fields: Field[];
    types: Option<string>[];
};

export default function ProjectFieldsIndex({ fields, types }: Props) {
    const [editing, setEditing] = useState<Field | null>(null);
    const [open, setOpen] = useState(false);
    const [type, setType] = useState<string>('text');
    const [required, setRequired] = useState(false);
    const [optionsText, setOptionsText] = useState('');

    const startCreate = () => {
        setEditing(null);
        setType('text');
        setRequired(false);
        setOptionsText('');
        setOpen(true);
    };

    const startEdit = (field: Field) => {
        setEditing(field);
        setType(field.type);
        setRequired(field.is_required);
        setOptionsText(field.options.join('\n'));
        setOpen(true);
    };

    const parseOptions = (): string[] =>
        optionsText
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== '');

    return (
        <PageBody>
            <Head title="Project fields" />

            <PageHeader
                title="Project fields"
                description="Extra metadata collected on every project."
                actions={
                    <Button onClick={startCreate}>
                        <Plus /> New field
                    </Button>
                }
            />

            {fields.length === 0 ? (
                <EmptyState
                    icon="folders"
                    title="No custom fields"
                    description="Add fields such as client, project type or technology."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Label</TableHead>
                                <TableHead>Key</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Required</TableHead>
                                <TableHead className="text-right">
                                    Values
                                </TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {fields.map((f) => (
                                <TableRow key={f.id}>
                                    <TableCell className="font-medium">
                                        {f.label}
                                    </TableCell>
                                    <TableCell className="font-mono text-xs">
                                        {f.key}
                                    </TableCell>
                                    <TableCell>{f.type_label}</TableCell>
                                    <TableCell>
                                        {f.is_required ? 'Yes' : 'No'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {f.values_count}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${f.label}`}
                                                onClick={() => startEdit(f)}
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${f.label}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title={`Delete ${f.label}?`}
                                                description="Values saved on projects are deleted too."
                                                action={ProjectFieldController.destroy(
                                                    f.id,
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
                            {editing ? `Edit ${editing.label}` : 'New field'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? ProjectFieldController.update.form(editing.id)
                            : ProjectFieldController.store.form())}
                        transform={(data) => ({
                            ...data,
                            is_required: required,
                            ...(type === 'select'
                                ? { options: parseOptions() }
                                : {}),
                        })}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Label"
                                    htmlFor="label"
                                    error={errors.label}
                                    required
                                >
                                    <Input
                                        id="label"
                                        name="label"
                                        defaultValue={editing?.label}
                                        required
                                    />
                                </FormField>
                                {!editing && (
                                    <>
                                        <FormField
                                            label="Key"
                                            htmlFor="key"
                                            error={errors.key}
                                            hint="Lowercase letters, digits and underscores. Cannot change later."
                                            required
                                        >
                                            <Input
                                                id="key"
                                                name="key"
                                                required
                                            />
                                        </FormField>
                                        <FormField
                                            label="Type"
                                            htmlFor="type"
                                            error={errors.type}
                                            hint="Cannot change later."
                                            required
                                        >
                                            <OptionSelect<string>
                                                id="type"
                                                name="type"
                                                value={type}
                                                options={types}
                                                onChange={(v) =>
                                                    setType(v ?? 'text')
                                                }
                                            />
                                        </FormField>
                                    </>
                                )}
                                {type === 'select' && (
                                    <FormField
                                        label="Options"
                                        htmlFor="options"
                                        error={errors.options}
                                        hint="One option per line."
                                        required
                                    >
                                        <Textarea
                                            id="options"
                                            rows={4}
                                            value={optionsText}
                                            onChange={(e) =>
                                                setOptionsText(e.target.value)
                                            }
                                        />
                                    </FormField>
                                )}
                                <FormField
                                    label="Sort order"
                                    htmlFor="sort"
                                    error={errors.sort}
                                >
                                    <Input
                                        id="sort"
                                        name="sort"
                                        type="number"
                                        min={0}
                                        defaultValue={editing?.sort ?? 0}
                                    />
                                </FormField>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="is_required"
                                        checked={required}
                                        onCheckedChange={(v) =>
                                            setRequired(v === true)
                                        }
                                    />
                                    <Label htmlFor="is_required">
                                        Required on every project
                                    </Label>
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing
                                            ? 'Save changes'
                                            : 'Create field'}
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

ProjectFieldsIndex.layout = {
    breadcrumbs: [
        { title: 'Projects', href: projectsIndex() },
        { title: 'Fields', href: index() },
    ],
};
