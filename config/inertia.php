<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [
        // First page of a visit rendered to HTML on the server (SEO, link
        // previews; see resources/js/ssr.tsx). Off by default: when on and
        // the SSR server is down, every full page load waits for it before
        // falling back to browser rendering. Tests keep it off (phpunit.xml).
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),

        // Production: the Node SSR server (bootstrap/ssr/ssr.js).
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        // Dev: the Vite dev server renders at /__inertia_ssr. public/hot
        // says http://localhost:5173, which is the BROWSER's view; from
        // the app container Vite is http://vite:5173.
        'hot_url' => env('INERTIA_SSR_HOT_URL'),

        // A slow render must not hold the page for long: after this many
        // seconds the page is sent without SSR (the browser renders it).
        'timeout' => (int) env('INERTIA_SSR_TIMEOUT', 3),

        // Production: skip SSR entirely when the bundle was never built.
        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),

        'throw_on_error' => false,

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
