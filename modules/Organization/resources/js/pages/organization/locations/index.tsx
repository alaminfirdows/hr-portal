import { Form, Head } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import LocationController from '@/actions/Modules/Organization/Http/Controllers/LocationController';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { EmptyState } from '@/components/portal/empty-state';
import { FormField } from '@/components/portal/form-field';
import { PageBody, PageHeader } from '@/components/portal/page-header';
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
import { OptionSelect } from '@/components/portal/option-select';
import { index } from '@/routes/organization/locations';

type Location = {
    id: number;
    name: string;
    address: string | null;
    timezone: string | null;
    employees_count: number;
};

type Props = {
    locations: Location[];
    timezones: string[];
};

export default function LocationsIndex({ locations, timezones }: Props) {
    const [editing, setEditing] = useState<Location | null>(null);
    const [open, setOpen] = useState(false);
    const timezoneOptions = timezones.map((tz) => ({ value: tz, label: tz }));

    const startCreate = () => {
        setEditing(null);
        setOpen(true);
    };

    const startEdit = (location: Location) => {
        setEditing(location);
        setOpen(true);
    };

    return (
        <PageBody>
            <Head title="Locations" />

            <PageHeader
                title="Locations"
                description="Offices and sites where employees work."
                actions={
                    <Button onClick={startCreate}>
                        <Plus /> New location
                    </Button>
                }
            />

            {locations.length === 0 ? (
                <EmptyState
                    icon="map-pin"
                    title="No locations yet"
                    description="Create the first location."
                />
            ) : (
                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Address</TableHead>
                                <TableHead>Timezone</TableHead>
                                <TableHead className="text-right">
                                    Employees
                                </TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {locations.map((location) => (
                                <TableRow key={location.id}>
                                    <TableCell className="font-medium">
                                        {location.name}
                                    </TableCell>
                                    <TableCell>
                                        {location.address ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {location.timezone ?? '—'}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {location.employees_count}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Edit ${location.name}`}
                                                onClick={() =>
                                                    startEdit(location)
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            <ConfirmAction
                                                trigger={
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Delete ${location.name}`}
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                }
                                                title={`Delete ${location.name}?`}
                                                description="Locations with employees cannot be deleted."
                                                action={LocationController.destroy(
                                                    location.id,
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
                            {editing ? `Edit ${editing.name}` : 'New location'}
                        </DialogTitle>
                    </DialogHeader>
                    <Form
                        key={editing?.id ?? 'new'}
                        {...(editing
                            ? LocationController.update.form(editing.id)
                            : LocationController.store.form())}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="grid gap-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <FormField
                                    label="Name"
                                    htmlFor="name"
                                    error={errors.name}
                                    required
                                >
                                    <Input
                                        id="name"
                                        name="name"
                                        defaultValue={editing?.name}
                                        required
                                    />
                                </FormField>
                                <FormField
                                    label="Address"
                                    htmlFor="address"
                                    error={errors.address}
                                >
                                    <Input
                                        id="address"
                                        name="address"
                                        defaultValue={editing?.address ?? ''}
                                    />
                                </FormField>
                                <FormField
                                    label="Timezone"
                                    htmlFor="timezone"
                                    error={errors.timezone}
                                >
                                    <OptionSelect
                                        id="timezone"
                                        name="timezone"
                                        value={editing?.timezone ?? null}
                                        options={timezoneOptions}
                                        allowEmpty
                                        emptyLabel="Company default"
                                    />
                                </FormField>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing
                                            ? 'Save changes'
                                            : 'Create location'}
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

LocationsIndex.layout = {
    breadcrumbs: [{ title: 'Locations', href: index() }],
};
