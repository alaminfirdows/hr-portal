import type { ReactNode } from 'react';
import { DynamicIcon } from '@/components/portal/dynamic-icon';

export function EmptyState({
    icon = 'folders',
    title,
    description,
    action,
}: {
    icon?: string;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-10 text-center">
            <DynamicIcon name={icon} className="size-8 text-muted-foreground" />
            <p className="font-medium">{title}</p>
            {description && (
                <p className="max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action && <div className="mt-2">{action}</div>}
        </div>
    );
}
