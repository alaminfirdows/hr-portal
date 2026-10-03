import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DynamicIcon } from '@/components/portal/dynamic-icon';

export function StatCard({
    title,
    value,
    caption,
    href,
    icon,
}: {
    title: string;
    value: string | number;
    caption?: string | null;
    href?: string | null;
    icon?: string;
}) {
    const body = (
        <Card className="h-full transition-colors hover:bg-muted/40">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
                {icon && (
                    <DynamicIcon
                        name={icon}
                        className="size-4 text-muted-foreground"
                    />
                )}
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold">{value}</div>
                {caption && (
                    <p className="mt-1 text-xs text-muted-foreground">
                        {caption}
                    </p>
                )}
            </CardContent>
        </Card>
    );

    return href ? (
        <Link href={href} className="block h-full">
            {body}
        </Link>
    ) : (
        body
    );
}
