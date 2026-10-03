import { createInertiaApp, router } from '@inertiajs/react';
import type { ComponentType } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

// Company name comes from organization settings via the shared `name` prop.
let appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('navigate', (event) => {
    const name = event.detail.page.props.name;

    if (typeof name === 'string' && name !== '') {
        appName = name;
    }
});

type PageModule = { default: ComponentType };

/**
 * Pages live in resources/js/pages and in each module's
 * modules/<Name>/resources/js/pages. A page name maps to the first file
 * whose path ends with /resources/js/pages/<name>.tsx.
 */
const pages = {
    ...import.meta.glob<PageModule>('./pages/**/*.tsx'),
    ...import.meta.glob<PageModule>(
        '../../modules/*/resources/js/pages/**/*.tsx',
    ),
};

async function resolvePage(name: string): Promise<ComponentType> {
    const suffix = `/resources/js/pages/${name}.tsx`;
    const loader =
        pages[`./pages/${name}.tsx`] ??
        pages[Object.keys(pages).find((path) => path.endsWith(suffix)) ?? ''];

    if (!loader) {
        throw new Error(`Page not found: ${name}`);
    }

    return (await loader()).default;
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePage(name),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
            case name.startsWith('identity/invitations/'):
                return AuthLayout;
            case name === 'settings/organization':
                return AppLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
