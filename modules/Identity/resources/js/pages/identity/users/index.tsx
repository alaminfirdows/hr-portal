import { Form, Head } from '@inertiajs/react';
import {
    MailPlus,
    RotateCw,
    ShieldCheck,
    UserCheck,
    UserPlus,
    UserX,
} from 'lucide-react';
import { useState } from 'react';
import UserController from '@/actions/Modules/Identity/Http/Controllers/UserController';
import { ConfirmAction } from '@/components/portal/confirm-action';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { formatDate, timeAgo } from '@/lib/format';
import { index } from '@/routes/identity/users';
import type { Option, Paginated } from '@/types';

type RoleRef = { id: number; name: string; slug: string };

type UserRow = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    last_login_at: string | null;
    roles: RoleRef[];
    pending_invitation: string | null;
};

type Props = {
    users: Paginated<UserRow>;
    roles: RoleRef[];
    filters: { search: string; status: string };
};

const STATUS_OPTIONS: Option<string>[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

function statusOf(user: UserRow): { label: string; color: string } {
    if (user.is_active) {
        return { label: 'Active', color: 'green' };
    }

    if (user.pending_invitation) {
        return { label: 'Invited', color: 'amber' };
    }

    return { label: 'Inactive', color: 'gray' };
}

function RoleChecks({
    roles,
    name,
    valueOf,
    initial,
    idPrefix,
}: {
    roles: RoleRef[];
    name: string;
    valueOf: (role: RoleRef) => string | number;
    initial: (string | number)[];
    idPrefix: string;
}) {
    const [selected, setSelected] = useState<(string | number)[]>(initial);

    const toggle = (value: string | number, checked: boolean) =>
        setSelected((current) =>
            checked ? [...current, value] : current.filter((v) => v !== value),
        );

    return (
        <div className="grid max-h-56 gap-2 overflow-y-auto rounded-md border p-3">
            {roles.map((role) => {
                const value = valueOf(role);
                const id = `${idPrefix}-${role.id}`;

                return (
                    <div key={role.id} className="flex items-center gap-2">
                        <Checkbox
                            id={id}
                            checked={selected.includes(value)}
                            onCheckedChange={(checked) =>
                                toggle(value, checked === true)
                            }
                        />
                        <Label htmlFor={id} className="font-normal">
                            {role.name}
                        </Label>
                        {selected.includes(value) && (
                            <input
                                type="hidden"
                                name={`${name}[]`}
                                value={value}
                            />
                        )}
                    </div>
                );
            })}
        </div>
    );
}

export default function UsersIndex({ users, roles, filters }: Props) {
    const can = useCan();
    const [inviteOpen, setInviteOpen] = useState(false);
    const [editing, setEditing] = useState<UserRow | null>(null);

    const patch = (change: Record<string, string | null>) =>
        applyFilters(filters, change);

    return (
        <PageBody>
            <Head title="Users" />

            <PageHeader
                title="Users"
                description="Invite people, manage access and account status."
                actions={
                    <Button onClick={() => setInviteOpen(true)}>
                        <UserPlus /> Invite user
                    </Button>
                }
            />

            <FilterBar
                active={Boolean(filters.search || filters.status)}
                onReset={() => applyFilters({}, {})}
            >
                <SearchInput
                    value={filters.search}
                    onSearch={(search) => patch({ search })}
                    placeholder="Search name or email"
                />
                <FilterSelect
                    value={filters.status || null}
                    options={STATUS_OPTIONS}
                    placeholder="Statuses"
                    onChange={(status) => patch({ status })}
                />
            </FilterBar>

            {users.data.length === 0 ? (
                <EmptyState
                    icon="users"
                    title="No users found"
                    description="Try different filters or invite a new user."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>User</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Roles</TableHead>
                                <TableHead>Last login</TableHead>
                                <TableHead className="w-32" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((user) => {
                                const status = statusOf(user);

                                return (
                                    <TableRow key={user.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {user.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {user.email}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                label={status.label}
                                                color={status.color}
                                            />
                                            {!user.is_active &&
                                                user.pending_invitation && (
                                                    <div className="mt-1 text-xs text-muted-foreground">
                                                        Expires{' '}
                                                        {formatDate(
                                                            user.pending_invitation,
                                                        )}
                                                    </div>
                                                )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {user.roles.length === 0 ? (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                ) : (
                                                    user.roles.map((role) => (
                                                        <Badge
                                                            key={role.id}
                                                            variant="outline"
                                                        >
                                                            {role.name}
                                                        </Badge>
                                                    ))
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {user.last_login_at
                                                ? timeAgo(user.last_login_at)
                                                : 'Never'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                {can('roles.manage') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Edit roles for ${user.name}`}
                                                        onClick={() =>
                                                            setEditing(user)
                                                        }
                                                    >
                                                        <ShieldCheck />
                                                    </Button>
                                                )}
                                                {!user.is_active && (
                                                    <ConfirmAction
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Resend invitation to ${user.name}`}
                                                            >
                                                                <RotateCw />
                                                            </Button>
                                                        }
                                                        title={`Resend invitation to ${user.email}?`}
                                                        description="A new invitation link will be emailed."
                                                        action={UserController.resend(
                                                            user.id,
                                                        )}
                                                        confirmLabel="Resend"
                                                    />
                                                )}
                                                {user.is_active ? (
                                                    <ConfirmAction
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Deactivate ${user.name}`}
                                                            >
                                                                <UserX />
                                                            </Button>
                                                        }
                                                        title={`Deactivate ${user.name}?`}
                                                        description="They will be signed out and unable to log in."
                                                        action={UserController.deactivate(
                                                            user.id,
                                                        )}
                                                        confirmLabel="Deactivate"
                                                        destructive
                                                    />
                                                ) : (
                                                    <ConfirmAction
                                                        trigger={
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label={`Activate ${user.name}`}
                                                            >
                                                                <UserCheck />
                                                            </Button>
                                                        }
                                                        title={`Activate ${user.name}?`}
                                                        action={UserController.activate(
                                                            user.id,
                                                        )}
                                                        confirmLabel="Activate"
                                                    />
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PaginationLinks paginator={users} />

            <Dialog open={inviteOpen} onOpenChange={setInviteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Invite user</DialogTitle>
                        <DialogDescription>
                            They get an email to set a password and join.
                        </DialogDescription>
                    </DialogHeader>
                    {inviteOpen && (
                        <Form
                            {...UserController.store.form()}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setInviteOpen(false)}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormField
                                        label="Name"
                                        htmlFor="invite-name"
                                        error={errors.name}
                                        required
                                    >
                                        <Input
                                            id="invite-name"
                                            name="name"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="Email"
                                        htmlFor="invite-email"
                                        error={errors.email}
                                        required
                                    >
                                        <Input
                                            id="invite-email"
                                            name="email"
                                            type="email"
                                            required
                                        />
                                    </FormField>
                                    <FormField
                                        label="Roles"
                                        htmlFor="invite-roles"
                                        error={errors.roles}
                                    >
                                        <RoleChecks
                                            roles={roles}
                                            name="roles"
                                            valueOf={(role) => role.slug}
                                            initial={[]}
                                            idPrefix="invite-role"
                                        />
                                    </FormField>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            <MailPlus /> Send invitation
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Edit roles{editing ? ` for ${editing.name}` : ''}
                        </DialogTitle>
                    </DialogHeader>
                    {editing && (
                        <Form
                            key={editing.id}
                            {...UserController.roles.form(editing.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <FormField
                                        label="Roles"
                                        htmlFor="edit-roles"
                                        error={errors.roles}
                                    >
                                        <RoleChecks
                                            roles={roles}
                                            name="roles"
                                            valueOf={(role) => role.id}
                                            initial={editing.roles.map(
                                                (role) => role.id,
                                            )}
                                            idPrefix="edit-role"
                                        />
                                    </FormField>
                                    <DialogFooter>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save roles
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </PageBody>
    );
}

UsersIndex.layout = {
    breadcrumbs: [{ title: 'Users', href: index() }],
};
