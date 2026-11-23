import { createInertiaApp } from '@inertiajs/react';
import { initializeTheme } from '@/hooks/use-appearance';
import { layout, title, withApp } from '@/inertia-options';

void createInertiaApp({
    title,
    layout,
    strictMode: true,
    withApp,
    progress: {
        // Zetago primary (see --primary in resources/css/app.css)
        color: '#0d5c55',
    },
});

// This will set light / dark mode on load...
initializeTheme();
