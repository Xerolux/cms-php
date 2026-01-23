<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GraphQL Route
    |--------------------------------------------------------------------------
    |
    | The route to the GraphQL endpoint.
    |
    */
    'route' => [
        'prefix' => 'graphql',
        'middleware' => ['web'], // Auth handled per query/mutation
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Location
    |--------------------------------------------------------------------------
    |
    | The path to the GraphQL schema file.
    |
    */
    'schema' => [
        'register' => base_path('graphql/schema.graphql'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    | These namespaces are used to look up classes for the schema.
    |
    */
    'namespaces' => [
        'models' => ['App', 'App\\Models'],
        'queries' => 'App\\GraphQL\\Queries',
        'mutations' => 'App\\GraphQL\\Mutations',
        'subscriptions' => 'App\\GraphQL\\Subscriptions',
        'interfaces' => 'App\\GraphQL\\Interfaces',
        'types' => 'App\\GraphQL\\Types',
        'scalars' => 'App\\GraphQL\\Scalars',
        'directives' => 'App\\GraphQL\\Directives',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Control the security features of Lighthouse.
    |
    */
    'security' => [
        'validation' => [
            'use_input' => 'always',
            'use_rules' => 'always',
        ],
        'fire_extending_queries' => env('LIGHTHOUSE_FIRE_EXTENDING_QUERIES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Limits for pagination.
    |
    */
    'pagination' => [
        'max_count' => 100,
        'default_count' => 20,
        'default_arguments' => [
            'first' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug
    |--------------------------------------------------------------------------
    |
    | Enable debug mode (includes QueryStack in error messages).
    |
    */
    'debug' => env('LIGHTHOUSE_DEBUG', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Error Handlers
    |--------------------------------------------------------------------------
    |
    | Register error handlers for GraphQL.
    |
    */
    'error_handlers' => [
        Nuwave\Lighthouse\Execution\ErrorHandler::class,
        // You can register custom error handlers here
        // App\GraphQL\ErrorHandler::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    |
    | Configuration for broadcast subscriptions.
    |
    */
    'subscriptions' => [
        'storage' => env('LIGHTHOUSE_SUBSCRIPTION_STORAGE', 'redis'),
        'broadcaster' => 'pusher',
        'broadcasts' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Apollo Federation
    |--------------------------------------------------------------------------
    |
    | Configuration for Apollo Federation.
    |
    */
    'federation' => [
        'enabled' => false,
        'entities_url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | GraphQL Playground
    |--------------------------------------------------------------------------
    |
    | Enable GraphQL Playground in production.
    |
    */
    'playground' => env('LIGHTHOUSE_PLAYGROUND', env('APP_ENV') !== 'production'),

    /*
    |--------------------------------------------------------------------------
    | GraphQL IDE
    |--------------------------------------------------------------------------
    |
    | Configure which GraphQL IDE to use.
    |
    */
    'ide' => env('LIGHTHOUSE_IDE', 'playground'),
];
