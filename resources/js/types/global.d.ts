import type { Auth } from '@/types/auth';
import type { NavGroup, NotificationSummary } from '@/types/portal';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            navigation: NavGroup[];
            notifications: NotificationSummary | null;
            [key: string]: unknown;
        };
    }
}
