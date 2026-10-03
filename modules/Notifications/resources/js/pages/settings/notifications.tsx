import { Head, useForm } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { update } from '@/routes/notifications/preferences';

type NotificationType = {
    type: string;
    module: string;
    description: string;
    in_app: boolean;
    email: boolean;
};

type Props = {
    types: NotificationType[];
};

type Preference = { type: string; in_app: boolean; email: boolean };

export default function NotificationSettings({ types }: Props) {
    const form = useForm<{ preferences: Preference[] }>({
        preferences: types.map(({ type, in_app, email }) => ({
            type,
            in_app,
            email,
        })),
    });

    const toggle = (i: number, key: 'in_app' | 'email', value: boolean) =>
        form.setData(
            'preferences',
            form.data.preferences.map((p, idx) =>
                idx === i ? { ...p, [key]: value } : p,
            ),
        );

    const modules = [...new Set(types.map((t) => t.module))];

    return (
        <>
            <Head title="Notification settings" />

            <h1 className="sr-only">Notification settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Notification preferences"
                    description="Choose how you want to be notified for each type of event"
                />

                <form
                    className="space-y-6"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(update().url, { preserveScroll: true });
                    }}
                >
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Notification</TableHead>
                                    <TableHead className="w-24 text-center">
                                        In-app
                                    </TableHead>
                                    <TableHead className="w-24 text-center">
                                        Email
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {modules.map((module) => (
                                    <ModuleRows
                                        key={module}
                                        module={module}
                                        types={types}
                                        prefs={form.data.preferences}
                                        onToggle={toggle}
                                    />
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    <InputError message={form.errors.preferences} />

                    <Button type="submit" disabled={form.processing}>
                        Save preferences
                    </Button>
                </form>
            </div>
        </>
    );
}

function ModuleRows({
    module,
    types,
    prefs,
    onToggle,
}: {
    module: string;
    types: NotificationType[];
    prefs: Preference[];
    onToggle: (i: number, key: 'in_app' | 'email', value: boolean) => void;
}) {
    return (
        <>
            <TableRow className="bg-muted/40 hover:bg-muted/40">
                <TableCell
                    colSpan={3}
                    className="text-xs font-semibold text-muted-foreground uppercase"
                >
                    {module}
                </TableCell>
            </TableRow>
            {types.map((t, i) =>
                t.module === module ? (
                    <TableRow key={t.type}>
                        <TableCell className="whitespace-normal">
                            {t.description}
                        </TableCell>
                        <TableCell className="text-center">
                            <Switch
                                checked={prefs[i].in_app}
                                onCheckedChange={(v) =>
                                    onToggle(i, 'in_app', v)
                                }
                                aria-label={`${t.description}: in-app`}
                            />
                        </TableCell>
                        <TableCell className="text-center">
                            <Switch
                                checked={prefs[i].email}
                                onCheckedChange={(v) => onToggle(i, 'email', v)}
                                aria-label={`${t.description}: email`}
                            />
                        </TableCell>
                    </TableRow>
                ) : null,
            )}
        </>
    );
}
