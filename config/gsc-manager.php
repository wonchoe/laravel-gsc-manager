<?php

return [
    'credentials_path' => storage_path('app/private/gsc-keys'),

    'auto_approve_discovered_sites' => false,

    'default_scope' => 'readonly',

    'scopes' => [
        'readonly' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'full' => 'https://www.googleapis.com/auth/webmasters',
        'indexing' => 'https://www.googleapis.com/auth/indexing',
    ],

    'analytics' => [
        'default_dimensions' => ['date', 'query', 'page', 'country', 'device'],
        'default_types' => ['web', 'image', 'video', 'news', 'discover', 'googleNews'],
        'days_back' => 3,
        'row_limit' => 25000,
        'paginate' => true,
        'max_pages_per_query' => 20,
        'data_state' => 'final',
        'aggregation_type' => 'auto',
    ],

    'sitemaps' => [
        'sync_enabled' => true,
        'submit_enabled' => false,
        'delete_enabled' => false,
    ],

    'url_inspection' => [
        'enabled' => true,
        'default_language_code' => 'en-US',
        'daily_limit_per_site' => 2000,
        'qpm_limit_per_site' => 600,
    ],

    'indexing_api' => [
        'enabled' => false,
        'allowed_content_only' => true,
        'allowed_types' => ['JobPosting', 'BroadcastEvent'],
    ],

    // Built-in API routes are OFF by default and gated by web+auth when enabled.
    // They include credential listing + sitemap delete, so never expose them unauthenticated.
    'routes' => [
        'enabled' => false,
        'prefix' => 'api/gsc',
        'name_prefix' => 'gsc-manager.',
        'middleware' => ['web', 'auth'],
    ],

    'rate_limits' => [
        'sleep_on_quota_seconds' => 15,
        'max_retries' => 3,
        'retry_sleep_seconds' => 5,
    ],
];
