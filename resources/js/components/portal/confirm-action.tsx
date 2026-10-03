import { router } from '@inertiajs/react';
import type { RouteDefinition } from '@/wayfinder';
import { useState } from 'react';
import type { ReactNode } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';

/**
 * Asks for confirmation, then sends the request (any method) to a Wayfinder route.
 */
export function ConfirmAction({
    trigger,
    title,
    description,
    action,
    data,
    confirmLabel = 'Confirm',
    destructive = false,
}: {
    trigger: ReactNode;
    title: string;
    description?: ReactNode;
    action: RouteDefinition<'post' | 'put' | 'patch' | 'delete'>;
    data?: Record<string, string | number | boolean | null>;
    confirmLabel?: string;
    destructive?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);

    const confirm = () => {
        router.visit(action.url, {
            method: action.method,
            data: data ?? {},
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => {
                setBusy(false);
                setOpen(false);
            },
        });
    };

    return (
        <AlertDialog open={open} onOpenChange={setOpen}>
            <AlertDialogTrigger asChild>{trigger}</AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description && (
                        <AlertDialogDescription>
                            {description}
                        </AlertDialogDescription>
                    )}
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={busy}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        disabled={busy}
                        onClick={(e) => {
                            e.preventDefault();
                            confirm();
                        }}
                        className={
                            destructive
                                ? buttonVariants({ variant: 'destructive' })
                                : undefined
                        }
                    >
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
