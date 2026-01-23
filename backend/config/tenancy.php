<?php

declare(strict_types=1);

return [
    'tenant_model' => \App\Models\Tenant::class,

    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        // Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
    ],

    'features' => [
        // Stancl\Tenancy\Features\UniversalRoutes::class,
        Stancl\Tenancy\Features\TenantConfig::class,
        Stancl\Tenancy\Features\TenantRedirect::class,
        // Stancl\Tenancy\Features\TelescopeTags::class,
    ],

    'storage' => [
        'disks' => [
            'local',
            'public',
            // 's3',
        ],
        'root_override' => [
            // Disks whose root directories should be overriden to %storage_path%/app/%tenant_id%/...
            // Only relevant if you're using the FilesystemTenancyBootstrapper.
            'local' => '%storage_path%/app/%tenant_id%',
            'public' => '%storage_path%/app/public/%tenant_id%',
            // 's3' => '%storage_path%/app/%tenant_id%/s3',
        ],

        'asset_template_urls' => [
            'public' => '//%tenant_id%.%storage_domain%/storage',
        ],
    ],

    'middleware' => [
        'prevent_access_from_central_domains' => Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
        'initialize' => Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
    ],

    'exempt_domains' => [
        // 'localhost',
        // 'your-domain.com',
        // 'www.your-domain.com',
    ],

    'domain_identification' => [
        'central_domains' => [
            'localhost',
            'xquantoria.test',
            'www.xquantoria.test',
        ],
    ],

    'database' => [
        'based_on' => null,
        'prefix' => 'tenant',
        'suffix' => '',
        'template_tenant_connection' => null,
    ],

    'redis' => [
        'tenant_prefix' => 'tenant',
        'prefix_separator' => ':',
        'tenancy' => Stancl\Tenancy\RedisDriver::class,
    ],

    'queue' => [
        'tag_headers' => [
            'X-Tenant-ID',
        ],
    ],

    'hashed_ids' => false,

    'single_domain_mode' => false,

    // Subscription/Billing Plans
    'plans' => [
        'free' => [
            'name' => 'Free',
            'price' => 0,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'max_users' => 2,
            'max_storage_gb' => 1,
            'max_posts' => 10,
            'features' => [
                'basic_analytics',
                'basic_theme',
                'community_support',
            ],
            'limits' => [
                'media_per_post' => 5,
                'categories' => 3,
                'tags' => 10,
            ],
        ],
        'starter' => [
            'name' => 'Starter',
            'price' => 9.99,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'max_users' => 5,
            'max_storage_gb' => 10,
            'max_posts' => 100,
            'features' => [
                'basic_analytics',
                'custom_theme',
                'email_support',
                'custom_domain',
                'seo_tools',
            ],
            'limits' => [
                'media_per_post' => 20,
                'categories' => 10,
                'tags' => 50,
            ],
        ],
        'professional' => [
            'name' => 'Professional',
            'price' => 29.99,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'max_users' => 20,
            'max_storage_gb' => 50,
            'max_posts' => 1000,
            'features' => [
                'advanced_analytics',
                'custom_theme',
                'priority_support',
                'custom_domain',
                'seo_tools',
                'api_access',
                'backup',
                'workflow_automation',
            ],
            'limits' => [
                'media_per_post' => 50,
                'categories' => 50,
                'tags' => 200,
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price' => 99.99,
            'currency' => 'EUR',
            'interval' => 'monthly',
            'max_users' => -1, // unlimited
            'max_storage_gb' => 500,
            'max_posts' => -1, // unlimited
            'features' => [
                'advanced_analytics',
                'custom_theme',
                '24_7_support',
                'custom_domain',
                'seo_tools',
                'api_access',
                'backup',
                'workflow_automation',
                'white_label',
                'custom_integrations',
                'advanced_security',
                'dedicated_server',
            ],
            'limits' => [
                'media_per_post' => -1, // unlimited
                'categories' => -1,
                'tags' => -1,
            ],
        ],
    ],

    // Trial settings
    'trial' => [
        'enabled' => true,
        'duration_days' => 14,
        'plan' => 'professional', // Plan to use during trial
    ],
];
