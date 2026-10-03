import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import RoleController from '@/actions/Modules/AccessControl/Http/Controllers/RoleController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody, PageHeader } from '@/components/portal/page-header';
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
import { index } from '@/routes/access/roles';

type Role = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_super: boolean;
    is_system: boolean;
    users_count: number;
    permissions_count: number;
};

type Props = { roles: Role[] };

export default function RolesIndex({ roles }: Props) {
    return (
        <PageBody>
            <Head title="Roles" />

            <PageHeader
                title="Roles"
                description="Roles group permissions and scopes. Assign them to users."
                actions={
                    <Button asChild>
                        <Link href={RoleController.create()}>
                            <Plus /> New role
                        </Link>
                    </Button>
                }
            />

            {roles.length === 0 ? (
                <EmptyState icon="shield" title="No roles yet" />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead className="text-right">
                                    Users
                                </TableHead>
                                <TableHead className="text-right">
                                    Permissions
                                </TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {role.name}
                                        </div>
                                        {role.description && (
                                            <div className="line-clamp-1 text-xs text-muted-foreground">
                                                {role.description}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex gap-1">
                                            {role.is_super && (
                                                <StatusBadge
                                                    label="Super"
                                                    color="purple"
                                                />
                                            )}
                                            {role.is_system && (
                                                <StatusBadge
                                                    label="System"
                                                    color="blue"
                                                />
                                            )}
                                            {!role.is_super &&
                                                !role.is_system && (
                                                    <span className="text-muted-foreground">
                                                        Custom
                                                    </span>
                                                )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {role.users_count}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {role.is_super
                                            ? 'All'
                                            : role.permissions_count}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                asChild
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${role.name}`}
                                            >
                                                <Link
                                                    href={RoleController.edit(
                                                        role.id,
                                                    )}
                                                >
                                                    <Pencil />
                                                </Link>
                                            </Button>
                                            {!role.is_system && (
                                                <ConfirmAction
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`Delete ${role.name}`}
                                                        >
                                                            <Trash2 />
                                                        </Button>
                                                    }
                                                    title={`Delete ${role.name}?`}
                                                    description="Roles assigned to users cannot be deleted."
                                                    action={RoleController.destroy(
                                                        role.id,
                                                    )}
                                                    confirmLabel="Delete"
                                                    destructive
                                                />
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </PageBody>
    );
}

RolesIndex.layout = {
    breadcrumbs: [{ title: 'Roles', href: index() }],
};
