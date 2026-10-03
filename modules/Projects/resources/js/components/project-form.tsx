import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { RouteFormDefinition } from '@/wayfinder';
import type { FieldRow, FormOptions, ProjectRecord } from './types';

/** Shared create/edit form. Pass `project` to edit. */
export function ProjectForm({
    action,
    project,
    options,
    fields,
    cancelHref,
    submitLabel,
    nextCode,
}: {
    action: RouteFormDefinition<'post'>;
    project?: ProjectRecord;
    options: FormOptions;
    fields: FieldRow[];
    cancelHref: string;
    submitLabel: string;
    nextCode?: string;
}) {
    const [values, setValues] = useState<Record<string, string>>(
        Object.fromEntries(fields.map((f) => [f.key, f.value ?? ''])),
    );

    const setValue = (key: string, value: string) =>
        setValues((current) => ({ ...current, [key]: value }));

    return (
        <Form
            {...action}
            transform={(data) => ({ ...data, fields: values })}
            className="grid max-w-3xl gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Name"
                            htmlFor="name"
                            error={errors.name}
                            required
                        >
                            <Input
                                id="name"
                                name="name"
                                defaultValue={project?.name}
                                required
                            />
                        </FormField>
                        <FormField
                            label="Code"
                            htmlFor="code"
                            error={errors.code}
                            hint={
                                nextCode
                                    ? `Leave empty to use ${nextCode}.`
                                    : undefined
                            }
                        >
                            <Input
                                id="code"
                                name="code"
                                defaultValue={project?.code}
                            />
                        </FormField>
                    </div>

                    <FormField
                        label="Description"
                        htmlFor="description"
                        error={errors.description}
                    >
                        <Textarea
                            id="description"
                            name="description"
                            rows={4}
                            defaultValue={project?.description ?? ''}
                        />
                    </FormField>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Visibility"
                            htmlFor="visibility"
                            error={errors.visibility}
                            hint="Public: everyone. Restricted: listed, members open. Private: members only."
                            required
                        >
                            <OptionSelect
                                id="visibility"
                                name="visibility"
                                value={project?.visibility ?? 'public'}
                                options={options.visibilities}
                            />
                        </FormField>
                        <FormField
                            label="Priority"
                            htmlFor="priority"
                            error={errors.priority}
                            required
                        >
                            <OptionSelect
                                id="priority"
                                name="priority"
                                value={project?.priority ?? 'medium'}
                                options={options.priorities}
                            />
                        </FormField>
                        <FormField
                            label="Status"
                            htmlFor="status"
                            error={errors.status}
                        >
                            <OptionSelect
                                id="status"
                                name="status"
                                value={project?.status ?? 'planned'}
                                options={options.statuses}
                            />
                        </FormField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <FormField
                            label="Manager"
                            htmlFor="manager_employee_id"
                            error={errors.manager_employee_id}
                            hint="The manager is always a member and cannot be removed."
                        >
                            <OptionSelect
                                id="manager_employee_id"
                                name="manager_employee_id"
                                value={project?.manager_employee_id ?? null}
                                options={options.managers}
                                allowEmpty
                                emptyLabel="No manager"
                            />
                        </FormField>
                        <FormField
                            label="Client"
                            htmlFor="client"
                            error={errors.client}
                        >
                            <Input
                                id="client"
                                name="client"
                                defaultValue={project?.client ?? ''}
                            />
                        </FormField>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <FormField
                            label="Budget"
                            htmlFor="budget"
                            error={errors.budget}
                        >
                            <Input
                                id="budget"
                                name="budget"
                                type="number"
                                min="0"
                                step="0.01"
                                defaultValue={project?.budget ?? ''}
                            />
                        </FormField>
                        <FormField
                            label="Start date"
                            htmlFor="start_date"
                            error={errors.start_date}
                        >
                            <Input
                                id="start_date"
                                name="start_date"
                                type="date"
                                defaultValue={project?.start_date ?? ''}
                            />
                        </FormField>
                        <FormField
                            label="End date"
                            htmlFor="end_date"
                            error={errors.end_date}
                        >
                            <Input
                                id="end_date"
                                name="end_date"
                                type="date"
                                defaultValue={project?.end_date ?? ''}
                            />
                        </FormField>
                    </div>

                    {fields.length > 0 && (
                        <div className="grid gap-4 border-t pt-6 sm:grid-cols-2">
                            {fields.map((field) => {
                                const error = (
                                    errors as Record<string, string | undefined>
                                )[`fields.${field.key}`];
                                const id = `field_${field.key}`;

                                return (
                                    <FormField
                                        key={field.key}
                                        label={field.label}
                                        htmlFor={id}
                                        error={error}
                                        required={field.required}
                                    >
                                        {field.type === 'select' ? (
                                            <OptionSelect<string>
                                                id={id}
                                                value={
                                                    values[field.key] || null
                                                }
                                                options={field.options.map(
                                                    (o) => ({
                                                        value: o,
                                                        label: o,
                                                    }),
                                                )}
                                                onChange={(v) =>
                                                    setValue(field.key, v ?? '')
                                                }
                                                allowEmpty={!field.required}
                                                emptyLabel="Not set"
                                            />
                                        ) : (
                                            <Input
                                                id={id}
                                                type={
                                                    field.type === 'text'
                                                        ? 'text'
                                                        : field.type
                                                }
                                                step={
                                                    field.type === 'number'
                                                        ? 'any'
                                                        : undefined
                                                }
                                                value={values[field.key] ?? ''}
                                                onChange={(e) =>
                                                    setValue(
                                                        field.key,
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        )}
                                    </FormField>
                                );
                            })}
                        </div>
                    )}

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={cancelHref}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
