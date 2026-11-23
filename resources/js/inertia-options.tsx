import type { ReactElement } from 'react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import ShopLayout from '@/layouts/shop-layout';

/*
 * Shared by app.tsx (browser) and ssr.tsx (server). Both must render the
 * same tree: if the server HTML differs from the browser's first render,
 * React throws the server version away (hydration mismatch).
 */

const appName = import.meta.env.VITE_APP_NAME || 'Zetago';

export const title = (title: string): string =>
    title ? `${title} - ${appName}` : appName;

export const layout = (name: string) => {
    switch (true) {
        case name === 'dev/design-system':
            return null;
        case name.startsWith('shop/'):
            return ShopLayout;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
};

export const withApp = (app: ReactElement): ReactElement => (
    <TooltipProvider delayDuration={0}>
        {app}
        <Toaster />
    </TooltipProvider>
);
