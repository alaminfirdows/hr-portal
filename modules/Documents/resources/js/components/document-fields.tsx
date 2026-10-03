import { useState } from 'react';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { Option } from '@/types';
import { OptionChecklist } from './option-checklist';

export type DocumentOptions = {
    visibilities: Option<string>[];
    departments: Option<number>[];
    teams: Option<number>[];
    projects: Option<number>[];
    users: Option<number>[];
    employees: Option<number>[];
};

export type FolderRow = { id: number; name: string; parent_id: number | null };

export type DocumentDefaults = {
    title?: string;
    description?: string | null;
    folder_id?: number | null;
    category?: string | null;
    tags?: string[];
    visibility?: string;
    department_id?: number | null;
    team_id?: number | null;
    project_id?: number | null;
    employee_id?: number | null;
    expires_on?: string | null;
    url?: string | null;
    shared_with?: Option<number>[];
};

/**
 * Metadata fields shared by the upload, link and edit forms. Rendered inside an Inertia <Form>.
 */
export function DocumentFields({
    errors,
    options,
    folders,
    defaults = {},
    withUrl = false,
}: {
    errors: Record<string, string>;
    options: DocumentOptions;
    folders: FolderRow[];
    defaults?: DocumentDefaults;
    withUrl?: boolean;
}) {
    const [visibility, setVisibility] = useState(
        defaults.visibility ?? 'private',
    );

    return (
        <>
            {withUrl && (
                <FormField
                    label="Link"
                    htmlFor="url"
                    error={errors.url}
                    hint="Opens in a new tab. Nothing is stored in the portal."
                    required
                >
                    <Input
                        id="url"
                        name="url"
                        type="url"
                        placeholder="https://"
                        defaultValue={defaults.url ?? ''}
                        required
                    />
                </FormField>
            )}
            <FormField
                label="Title"
                htmlFor="title"
                error={errors.title}
                required
            >
                <Input
                    id="title"
                    name="title"
                    defaultValue={defaults.title}
                    required
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
                    defaultValue={defaults.description ?? ''}
                />
            </FormField>
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField
                    label="Folder"
                    htmlFor="folder_id"
                    error={errors.folder_id}
                >
                    <OptionSelect
                        id="folder_id"
                        name="folder_id"
                        value={defaults.folder_id ?? null}
                        options={folders.map((f) => ({
                            value: f.id,
                            label: f.name,
                        }))}
                        allowEmpty
                        emptyLabel="No folder"
                    />
                </FormField>
                <FormField
                    label="Category"
                    htmlFor="category"
                    error={errors.category}
                >
                    <Input
                        id="category"
                        name="category"
                        defaultValue={defaults.category ?? ''}
                    />
                </FormField>
                <FormField
                    label="Tags"
                    htmlFor="tags"
                    error={errors.tags}
                    hint="Comma separated."
                >
                    <Input
                        id="tags"
                        name="tags"
                        defaultValue={(defaults.tags ?? []).join(', ')}
                    />
                </FormField>
                <FormField
                    label="Expires on"
                    htmlFor="expires_on"
                    error={errors.expires_on}
                >
                    <Input
                        id="expires_on"
                        name="expires_on"
                        type="date"
                        defaultValue={defaults.expires_on ?? ''}
                    />
                </FormField>
            </div>
            <FormField
                label="Visible to"
                htmlFor="visibility"
                error={errors.visibility}
                required
            >
                <OptionSelect
                    id="visibility"
                    name="visibility"
                    value={visibility}
                    onChange={(v) => setVisibility(v ?? 'private')}
                    options={options.visibilities}
                />
            </FormField>
            {visibility === 'department' && (
                <FormField
                    label="Department"
                    htmlFor="target_department_id"
                    error={errors.target_department_id}
                    hint="Defaults to your own department. Only HR can pick another."
                >
                    <OptionSelect
                        id="target_department_id"
                        name="target_department_id"
                        value={defaults.department_id ?? null}
                        options={options.departments}
                        allowEmpty
                        emptyLabel="My department"
                    />
                </FormField>
            )}
            {visibility === 'team' && (
                <FormField
                    label="Team"
                    htmlFor="target_team_id"
                    error={errors.target_team_id}
                    hint="Defaults to your own team. Only HR can pick another."
                >
                    <OptionSelect
                        id="target_team_id"
                        name="target_team_id"
                        value={defaults.team_id ?? null}
                        options={options.teams}
                        allowEmpty
                        emptyLabel="My team"
                    />
                </FormField>
            )}
            {visibility === 'project' && (
                <FormField
                    label="Project"
                    htmlFor="target_project_id"
                    error={errors.target_project_id}
                    required
                >
                    <OptionSelect
                        id="target_project_id"
                        name="target_project_id"
                        value={defaults.project_id ?? null}
                        options={options.projects}
                        placeholder="Select a project…"
                    />
                </FormField>
            )}
            {visibility === 'users' && (
                <FormField label="People" error={errors.user_ids} required>
                    <OptionChecklist
                        name="user_ids"
                        options={options.users}
                        initial={(defaults.shared_with ?? []).map(
                            (u) => u.value,
                        )}
                    />
                </FormField>
            )}
            {options.employees.length > 0 && (
                <FormField
                    label="Belongs to employee"
                    htmlFor="employee_id"
                    error={errors.employee_id}
                    hint="Attach to an employee record (contract, ID…). That employee can open it."
                >
                    <OptionSelect
                        id="employee_id"
                        name="employee_id"
                        value={defaults.employee_id ?? null}
                        options={options.employees}
                        allowEmpty
                        emptyLabel="Nobody"
                    />
                </FormField>
            )}
        </>
    );
}
