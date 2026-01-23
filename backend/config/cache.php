<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    */

    'default' => env('CACHE_DRIVER', 'redis'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    */

    'stores' => [

        'apc' => [
            'driver' => 'apc',
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'table' => env('CACHE_TABLE', 'cache'),
            'connection' => env('CACHE_DB_CONNECTION'),
            'lock_connection' => env('CACHE_DB_LOCK_CONNECTION'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'cache'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    */

    'prefix' => env('CACHE_PREFIX', str_slug(env('APP_NAME', 'laravel'), '_') . '_cache_'),

    /*
    |--------------------------------------------------------------------------
    | Redis Cache Configuration
    |--------------------------------------------------------------------------
    */

    'redis' => [
        'replication' => env('REDIS_REPLICATION', false),
        'master' => [
            'host' => env('REDIS_MASTER_HOST', 'redis-master'),
            'port' => env('REDIS_MASTER_PORT', 6379),
            'password' => env('REDIS_MASTER_PASSWORD', null),
            'database' => env('REDIS_MASTER_DB', 0),
        ],
        'slave' => [
            'host' => env('REDIS_SLAVE_HOST', 'redis-slave'),
            'port' => env('REDIS_SLAVE_PORT', 6380),
            'password' => env('REDIS_SLAVE_PASSWORD', null),
            'database' => env('REDIS_SLAVE_DB', 0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Varnish Configuration
    |--------------------------------------------------------------------------
    */

    'varnish' => [
        'host' => env('VARNISH_HOST', 'varnish'),
        'port' => env('VARNISH_PORT', 6081),
        'admin_port' => env('VARNISH_ADMIN_PORT', 6082),
        'admin_secret' => env('VARNISH_ADMIN_SECRET'),
        'allowed_ips' => explode(',', env('VARNISH_ALLOWED_IPS', '127.0.0.1,::1')),
        'backend_host' => env('VARNISH_BACKEND_HOST', 'backend'),
        'backend_port' => env('VARNISH_BACKEND_PORT', 9000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Cache Configuration
    |--------------------------------------------------------------------------
    */

    'page' => [
        'enabled' => env('PAGE_CACHE_ENABLED', true),
        'default_ttl' => env('PAGE_CACHE_TTL', 3600),
        'compression_threshold' => env('PAGE_CACHE_COMPRESSION_THRESHOLD', 10240),
        'compression_enabled' => env('PAGE_CACHE_COMPRESSION_ENABLED', true),
        'ignore_query_params' => explode(',', env('PAGE_CACHE_IGNORE_QUERY_PARAMS', 'utm_source,utm_medium,utm_campaign,fbclid,gclid')),
        'ignore_cookies' => explode(',', env('PAGE_CACHE_IGNORE_COOKIES', 'bar,i18n')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Warming Configuration
    |--------------------------------------------------------------------------
    */

    'warming' => [
        'enabled' => env('CACHE_WARMING_ENABLED', true),
        'schedule' => env('CACHE_WARMING_SCHEDULE', '*/30 * * * *'),
        'batch_size' => env('CACHE_WARMING_BATCH_SIZE', 50),
        'delay_between_requests' => env('CACHE_WARMING_DELAY', 500), // milliseconds
        'popular_posts_limit' => env('CACHE_WARMING_POPULAR_LIMIT', 20),
        'sitemap_url' => env('CACHE_WARMING_SITEMAP_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP/2 Configuration
    |--------------------------------------------------------------------------
    */

    'http2' => [
        'enabled' => env('HTTP2_ENABLED', true),
        'push_enabled' => env('HTTP2_PUSH_ENABLED', true),
        'preload_critical_css' => env('HTTP2_PRELOAD_CRITICAL_CSS', true),
        'preload_fonts' => env('HTTP2_PRELOAD_FONTS', true),
        'preload_scripts' => env('HTTP2_PRELOAD_SCRIPTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Edge Computing Configuration
    |--------------------------------------------------------------------------
    */

    'edge' => [
        'enabled' => env('EDGE_COMPUTING_ENABLED', true),
        'cloudflare_enabled' => env('CLOUDFLARE_ENABLED', false),
        'cdn_url' => env('CDN_URL'),
        'cdn_host' => env('CDN_HOST'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Optimization Configuration
    |--------------------------------------------------------------------------
    */

    'query' => [
        'log_slow_queries' => env('LOG_SLOW_QUERIES', true),
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 100), // milliseconds
        'detect_n_plus_one' => env('DETECT_N_PLUS_ONE', true),
        'query_cache_enabled' => env('QUERY_CACHE_ENABLED', true),
        'query_cache_ttl' => env('QUERY_CACHE_TTL', 300),
    ],

];
