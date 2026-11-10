<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Las dos apps del navegador van SIEMPRE, se diga lo que se diga en .env: el SPA (4200) y
    // la landing (4300, §22). CORS_ALLOWED_ORIGINS solo AÑADE. Antes la variable sustituia a la
    // lista, y un .env copiado de .env.example (que solo nombraba el 4200) dejaba el alta de
    // la landing sin permiso en local — WALK-8, 2026-11-10.
    'allowed_origins' => array_values(array_unique(array_filter(array_merge(
        ['http://localhost:4200', 'http://localhost:4300'],
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    )))),

    // Matches GitHub Codespaces forwarded-port URLs, e.g.
    // https://<codespace-name>-4200.app.github.dev — the dev environment
    // (see claude.md → GitHub Codespaces).
    'allowed_origins_patterns' => [
        '#^https://.*\.(app\.github\.dev|githubpreview\.dev)$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
