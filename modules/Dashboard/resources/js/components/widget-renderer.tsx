import { Link } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { StatCard } from '@/components/portal/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { ListWidgetData, StatWidgetData, Widget } from '@/types';

type WidgetProps = { widget: Widget };

function StatWidget({ widget }: WidgetProps) {
    const data = widget.data as StatWidgetData;

    return (
        <StatCard
            title={widget.title}
            value={data.value}
            caption={data.caption}
            href={data.href}
        />
    );
}

function ListWidget({ widget }: WidgetProps) {
    const data = widget.data as ListWidgetData;
    const items = data.items ?? [];

    return (
        <Card className="h-full">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">
                    {widget.title}
                </CardTitle>
                {data.href && (
                    <Button asChild variant="ghost" size="sm">
                        <Link href={data.href}>View all</Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                {items.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {data.empty}
                    </p>
                ) : (
                    <ul className="divide-y">
                        {items.map((item) => {
                            const content = (
                                <div className="flex items-start justify-between gap-3 py-2">
                                    <div className="min-w-0">
                                        <p className="truncate text-sm font-medium">
                                            {item.title}
                                        </p>
                                        {item.subtitle && (
                                            <p className="truncate text-xs text-muted-foreground">
                                                {item.subtitle}
                                            </p>
                                        )}
                                    </div>
                                    {item.meta && (
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {item.meta}
                                        </span>
                                    )}
                                </div>
                            );

                            return (
                                <li key={item.id}>
                                    {item.href ? (
                                        <Link
                                            href={item.href}
                                            className="block hover:bg-muted/40"
                                        >
                                            {content}
                                        </Link>
                                    ) : (
                                        content
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

/** Registry: later modules can add widget types via registerWidgetType(). */
const registry: Record<string, ComponentType<WidgetProps>> = {
    stat: StatWidget,
    list: ListWidget,
};

export function registerWidgetType(
    type: string,
    component: ComponentType<WidgetProps>,
): void {
    registry[type] = component;
}

export function hasWidgetType(type: string): boolean {
    return type in registry;
}

export function WidgetRenderer({ widget }: WidgetProps) {
    const Component = registry[widget.type];

    return Component ? <Component widget={widget} /> : null;
}
