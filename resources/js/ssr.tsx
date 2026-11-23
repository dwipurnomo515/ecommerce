import { createInertiaApp } from '@inertiajs/react';
import { layout, title, withApp } from '@/inertia-options';

/*
 * Server-side rendering: turns the first page of a visit into real HTML,
 * so search engines and link previews see product names and prices
 * without running JavaScript. The browser then takes over (hydrates).
 *
 * @inertiajs/vite wraps this call: in dev the Vite server renders it at
 * /__inertia_ssr; `npm run build:ssr` produces bootstrap/ssr/ssr.js, a
 * small Node server (port 13714) for production.
 *
 * Runs in Node: no window, document or localStorage during render.
 */
void createInertiaApp({
    title,
    layout,
    strictMode: true,
    withApp,
});
