<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'deepl' => [
        'api_key' => env('DEEPL_API_KEY'),
        'url' => env('DEEPL_API_URL', 'https://api-free.deepl.com/v2/translate'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'api_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4'),
        'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 4000),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
    ],

];
