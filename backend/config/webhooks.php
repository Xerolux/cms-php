<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for the webhook system.
    |
    */

    'max_attempts' => env('WEBHOOK_MAX_ATTEMPTS', 3),

    'timeout' => env('WEBHOOK_TIMEOUT', 30),

    'retry_delay' => env('WEBHOOK_RETRY_DELAY', 60),

    'signature_algorithm' => env('WEBHOOK_SIGNATURE_ALGORITHM', 'sha256'),

    'signature_header' => env('WEBHOOK_SIGNATURE_HEADER', 'X-Webhook-Signature'),

    'timestamp_header' => env('WEBHOOK_TIMESTAMP_HEADER', 'X-Webhook-Timestamp'),

    'event_header' => env('WEBHOOK_EVENT_HEADER', 'X-Webhook-Event'),

    'delivery_header' => env('WEBHOOK_DELIVERY_HEADER', 'X-Webhook-Delivery'),

    /*
    |--------------------------------------------------------------------------
    | Available Webhook Events
    |--------------------------------------------------------------------------
    |
    | List of all available webhook events in the system.
    | You can add custom events here.
    |
    */

    'events' => [
        // Post Events
        'post.created' => [
            'name' => 'Post Created',
            'description' => 'Triggered when a new post is created',
            'category' => 'Posts',
        ],
        'post.updated' => [
            'name' => 'Post Updated',
            'description' => 'Triggered when a post is updated',
            'category' => 'Posts',
        ],
        'post.published' => [
            'name' => 'Post Published',
            'description' => 'Triggered when a post is published',
            'category' => 'Posts',
        ],
        'post.deleted' => [
            'name' => 'Post Deleted',
            'description' => 'Triggered when a post is deleted',
            'category' => 'Posts',
        ],
        'post.scheduled' => [
            'name' => 'Post Scheduled',
            'description' => 'Triggered when a post is scheduled for publishing',
            'category' => 'Posts',
        ],

        // User Events
        'user.created' => [
            'name' => 'User Created',
            'description' => 'Triggered when a new user is created',
            'category' => 'Users',
        ],
        'user.updated' => [
            'name' => 'User Updated',
            'description' => 'Triggered when a user is updated',
            'category' => 'Users',
        ],
        'user.deleted' => [
            'name' => 'User Deleted',
            'description' => 'Triggered when a user is deleted',
            'category' => 'Users',
        ],
        'user.login' => [
            'name' => 'User Login',
            'description' => 'Triggered when a user logs in',
            'category' => 'Users',
        ],
        'user.logout' => [
            'name' => 'User Logout',
            'description' => 'Triggered when a user logs out',
            'category' => 'Users',
        ],

        // Comment Events
        'comment.created' => [
            'name' => 'Comment Created',
            'description' => 'Triggered when a new comment is created',
            'category' => 'Comments',
        ],
        'comment.updated' => [
            'name' => 'Comment Updated',
            'description' => 'Triggered when a comment is updated',
            'category' => 'Comments',
        ],
        'comment.deleted' => [
            'name' => 'Comment Deleted',
            'description' => 'Triggered when a comment is deleted',
            'category' => 'Comments',
        ],
        'comment.approved' => [
            'name' => 'Comment Approved',
            'description' => 'Triggered when a comment is approved',
            'category' => 'Comments',
        ],

        // Category Events
        'category.created' => [
            'name' => 'Category Created',
            'description' => 'Triggered when a new category is created',
            'category' => 'Categories',
        ],
        'category.updated' => [
            'name' => 'Category Updated',
            'description' => 'Triggered when a category is updated',
            'category' => 'Categories',
        ],
        'category.deleted' => [
            'name' => 'Category Deleted',
            'description' => 'Triggered when a category is deleted',
            'category' => 'Categories',
        ],

        // Tag Events
        'tag.created' => [
            'name' => 'Tag Created',
            'description' => 'Triggered when a new tag is created',
            'category' => 'Tags',
        ],
        'tag.updated' => [
            'name' => 'Tag Updated',
            'description' => 'Triggered when a tag is updated',
            'category' => 'Tags',
        ],
        'tag.deleted' => [
            'name' => 'Tag Deleted',
            'description' => 'Triggered when a tag is deleted',
            'category' => 'Tags',
        ],

        // Media Events
        'media.uploaded' => [
            'name' => 'Media Uploaded',
            'description' => 'Triggered when media is uploaded',
            'category' => 'Media',
        ],
        'media.deleted' => [
            'name' => 'Media Deleted',
            'description' => 'Triggered when media is deleted',
            'category' => 'Media',
        ],

        // Form Events
        'form.submitted' => [
            'name' => 'Form Submitted',
            'description' => 'Triggered when a form is submitted',
            'category' => 'Forms',
        ],

        // Newsletter Events
        'newsletter.subscribed' => [
            'name' => 'Newsletter Subscribed',
            'description' => 'Triggered when someone subscribes to newsletter',
            'category' => 'Newsletter',
        ],
        'newsletter.unsubscribed' => [
            'name' => 'Newsletter Unsubscribed',
            'description' => 'Triggered when someone unsubscribes from newsletter',
            'category' => 'Newsletter',
        ],
        'newsletter.sent' => [
            'name' => 'Newsletter Sent',
            'description' => 'Triggered when a newsletter campaign is sent',
            'category' => 'Newsletter',
        ],

        // System Events
        'system.backup_completed' => [
            'name' => 'Backup Completed',
            'description' => 'Triggered when a system backup is completed',
            'category' => 'System',
        ],
        'system.error' => [
            'name' => 'System Error',
            'description' => 'Triggered when a system error occurs',
            'category' => 'System',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Categories
    |--------------------------------------------------------------------------
    |
    | Group events by categories for better organization in UI.
    |
    */

    'categories' => [
        'Posts' => [
            'icon' => 'document-text',
            'description' => 'Events related to blog posts',
        ],
        'Users' => [
            'icon' => 'users',
            'description' => 'Events related to user management',
        ],
        'Comments' => [
            'icon' => 'chat-bubble',
            'description' => 'Events related to comments',
        ],
        'Categories' => [
            'icon' => 'folder',
            'description' => 'Events related to categories',
        ],
        'Tags' => [
            'icon' => 'tag',
            'description' => 'Events related to tags',
        ],
        'Media' => [
            'icon' => 'photo',
            'description' => 'Events related to media files',
        ],
        'Forms' => [
            'icon' => 'document',
            'description' => 'Events related to form submissions',
        ],
        'Newsletter' => [
            'icon' => 'envelope',
            'description' => 'Events related to newsletters',
        ],
        'System' => [
            'icon' => 'cog',
            'description' => 'System-related events',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue connection and job settings for webhook delivery.
    |
    */

    'queue' => [
        'connection' => env('WEBHOOK_QUEUE_CONNECTION', config('queue.default')),
        'queue' => env('WEBHOOK_QUEUE_NAME', 'webhooks'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payload Configuration
    |--------------------------------------------------------------------------
    |
    | Configure what data should be included in webhook payloads.
    |
    */

    'payload' => [
        'include_timestamp' => true,
        'include_delivery_id' => true,
        'include_event_type' => true,
        'max_size' => 1048576, // 1MB in bytes
    ],
];
