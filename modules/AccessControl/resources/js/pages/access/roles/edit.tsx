import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import RoleController from '@/actions/Modules/AccessControl/Http/Controllers/RoleController';
import { FormField } from '@/components/portal/form-field';
import { OptionSelect } from '@/components/portal/option-select';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/access/roles';
import type { Option } from '@/types';

type RoleData = {
    id: number;
    name: string;
    description: string | null;
    is_super: boolean;
    is_system: boolean;
    permissions: Record<string, string>;
};

type Group = {
    module: string;
    permissions: { key: string; description: string | null }[];
};

type Props = {
    role: RoleData | null;
    groups: Group[];
    scopes: Option<string>[];
};

export default function RoleEdit({ role, groups, scopes }: Props) {
    const [granted, setGranted] = useState<Record<string, string>>(
        role?.permissions ?? {},
    );
    const isSuper = role?.is_super ?? false;
    const options: Option<string>[] = scopes;

    const setScope = (key: string, scope: string | null) =>
        setGranted((current) => {
            const next = { ...current };

            if (scope) {
                next[key] = scope;
            } else {
                delete next[key];
            }

            return next;
        });

    return (
        <PageBody>
            <Head title={role ? `Edit ${role.name}` : 'New role'} />

            <PageHeader
                title={role ? `Edit ${role.name}` : 'New role'}
                description="Choose what this role can do and how far it reaches."
                actions={
                    <Button asChild variant="outline">
                        <Link href={index()}>Back to roles</Link>
                    </Button>
                }
            />

            <Form
                key={role?.id ?? 'new'}
                {...(role
                    ? RoleController.update.form(role.id)
                    : RoleController.store.form())}
                options={{ preserveScroll: true }}
                className="grid max-w-4xl gap-6"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-4">
                            <FormField
                                label="Name"
                                htmlFor="name"
                                error={errors.name}
                                required
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={role?.name}
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
                                    defaultValue={role?.description ?? ''}
                                />
                            </FormField>
                        </div>

                        {isSuper && (
                            <Alert>
                                <AlertDescription>
                                    The super role has every permission with
                                    full scope. Its permissions cannot be
                                    changed.
                                </AlertDescription>
                            </Alert>
                        )}

                        <div className="grid gap-4">
                            {groups.map((group) => (
                                <Card key={group.module}>
                                    <CardHeader>
                                        <CardTitle className="text-base capitalize">
                                            {group.module}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="divide-y">
                                        {group.permissions.map((permission) => (
                                            <div
                                                key={permission.key}
                                                className="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="min-w-0">
                                                    <div className="font-mono text-sm">
                                                        {permission.key}
                                                    </div>
                                                    {permission.description && (
                                                        <div className="text-xs text-muted-foreground">
                                                            {
                                                                permission.description
                                                            }
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="w-full sm:w-44">
                                                    <OptionSelect
                                                        value={
                                                            isSuper
                                                                ? 'all'
                                                                : (granted[
                                                                      permission
                                                                          .key
                                                                  ] ?? null)
                                                        }
                                                        options={options}
                                                        onChange={(scope) =>
                                                            setScope(
                                                                permission.key,
                                                                scope,
                                                            )
                                                        }
                                                        allowEmpty
                                                        emptyLabel="None"
                                                        placeholder="None"
                                                        disabled={isSuper}
                                                    />
                                                    {!isSuper &&
                                                        granted[
                                                            permission.key
                                                        ] && (
                                                            <input
                                                                type="hidden"
                                                                name={`permissions[${permission.key}]`}
                                                                value={
                                                                    granted[
                                                                        permission
                                                                            .key
                                                                    ]
                                                                }
                                                            />
                                                        )}
                                                </div>
                                            </div>
                                        ))}
                                    </CardContent>
                                </Card>
                            ))}
                            {errors.permissions && (
                                <p className="text-sm text-destructive">
                                    {errors.permissions}
                                </p>
                            )}
                        </div>

                        <div>
                            <Button type="submit" disabled={processing}>
                                {role ? 'Save role' : 'Create role'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </PageBody>
    );
}

RoleEdit.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
