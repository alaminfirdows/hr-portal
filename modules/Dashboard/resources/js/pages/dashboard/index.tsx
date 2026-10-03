import { Head, usePage } from '@inertiajs/react';
import {
    hasWidgetType,
    WidgetRenderer,
} from '../../components/widget-renderer';
import { EmptyState } from '@/components/portal/empty-state';
import { PageBody, PageHeader } from '@/components/portal/page-header';
import { dashboard } from '@/routes';
import type { Widget } from '@/types';

type Props = {
    greeting: string;
    widgets: Widget[];
};

const SPAN: Record<number, string> = {
    1: 'lg:col-span-1',
    2: 'lg:col-span-2',
    3: 'lg:col-span-3',
    4: 'lg:col-span-4',
};

export default function DashboardIndex({ greeting, widgets }: Props) {
    const { auth } = usePage().props;
    const visible = widgets.filter((w) => hasWidgetType(w.type));
    const firstName = auth.user.name.split(' ')[0];

    return (
        <PageBody>
            <Head title="Dashboard" />
            <PageHeader
                title={`${greeting}, ${firstName}`}
                description="Here is what's happening today."
            />
            {visible.length === 0 ? (
                <EmptyState
                    icon="layout-dashboard"
                    title="Nothing to show yet"
                    description="Widgets will appear here as modules become available."
                />
            ) : (
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {visible.map((widget) => (
                        <div
                            key={widget.key}
                            className={`${widget.size >= 2 ? 'sm:col-span-2' : ''} ${SPAN[widget.size] ?? 'lg:col-span-1'}`}
                        >
                            <WidgetRenderer widget={widget} />
                        </div>
                    ))}
                </div>
            )}
        </PageBody>
    );
}

DashboardIndex.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};
