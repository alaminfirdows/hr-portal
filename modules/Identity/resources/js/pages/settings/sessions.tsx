import { Head } from '@inertiajs/react';
import { Laptop } from 'lucide-react';
import SessionController from '@/actions/Modules/Identity/Http/Controllers/SessionController';
import Heading from '@/components/heading';
import { ConfirmAction } from '@/components/portal/confirm-action';
import { StatusBadge } from '@/components/portal/status-badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/identity/sessions';

type BrowserSession = {
    id: string;
    ip_address: string | null;
    agent: string;
    is_current: boolean;
    last_active: string;
};

type Props = {
    sessions: BrowserSession[];
    supported: boolean;
};

export default function Sessions({ sessions, supported }: Props) {
    const hasOthers = sessions.some((session) => !session.is_current);

    return (
        <>
            <Head title="Browser sessions" />

            <h1 className="sr-only">Browser sessions</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Browser sessions"
                    description="Manage and sign out your active sessions on other browsers and devices"
                />

                {!supported ? (
                    <p className="text-sm text-muted-foreground">
                        Session management is not available with the current
                        session driver.
                    </p>
                ) : (
                    <>
                        <ul className="divide-y rounded-lg border">
                            {sessions.map((session) => (
                                <li
                                    key={session.id}
                                    className="flex items-center gap-3 p-4"
                                >
                                    <Laptop
                                        className="size-6 shrink-0 text-muted-foreground"
                                        aria-hidden
                                    />
                                    <div className="min-w-0 flex-1">
                                        <div className="text-sm font-medium">
                                            {session.agent}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {session.ip_address ?? 'Unknown IP'}{' '}
                                            · {session.last_active}
                                        </div>
                                    </div>
                                    {session.is_current && (
                                        <StatusBadge
                                            label="This device"
                                            color="green"
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>

                        <ConfirmAction
                            trigger={
                                <Button variant="outline" disabled={!hasOthers}>
                                    Log out other sessions
                                </Button>
                            }
                            title="Log out other sessions?"
                            description="You may be asked to confirm your password first."
                            action={SessionController.destroy()}
                            confirmLabel="Log out"
                            destructive
                        />
                    </>
                )}
            </div>
        </>
    );
}

Sessions.layout = {
    breadcrumbs: [{ title: 'Browser sessions', href: index() }],
};
