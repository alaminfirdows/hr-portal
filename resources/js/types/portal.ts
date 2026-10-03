export type Scope = 'own' | 'team' | 'department' | 'all';

export type NavLink = {
    title: string;
    href: string;
    icon: string;
    active: boolean;
};

export type NavGroup = {
    label: string;
    items: NavLink[];
};

export type PortalNotification = {
    id: string;
    type: string;
    title: string;
    body: string;
    url: string | null;
    icon: string;
    read_at: string | null;
    created_at: string | null;
};

export type NotificationSummary = {
    unread: number;
    recent: PortalNotification[];
};

export type Option<V = number | string> = {
    value: V;
    label: string;
};

export type PaginatorLink = {
    url: string | null;
    label: string;
    active: boolean;
};

/** Laravel LengthAwarePaginator serialized by Inertia. */
export type Paginated<T> = {
    data: T[];
    links: PaginatorLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};

export type Widget = {
    key: string;
    title: string;
    type: string; // 'stat' | 'list' | module-registered types
    size: 1 | 2 | 3 | 4;
    data: Record<string, unknown>;
};

export type StatWidgetData = {
    value: string | number;
    caption?: string | null;
    href?: string | null;
};

export type ListWidgetItem = {
    id: string | number;
    title: string;
    subtitle?: string | null;
    meta?: string | null;
    href?: string | null;
};

export type ListWidgetData = {
    items: ListWidgetItem[];
    empty: string;
    href?: string | null;
};
