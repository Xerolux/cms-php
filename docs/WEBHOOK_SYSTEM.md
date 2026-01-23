# Webhook System Documentation

## Overview

The XQUANTORIA Webhook System allows real-time event notifications to external services. When specific events occur in your CMS (like creating a post, user registration, etc.), webhooks automatically send HTTP POST requests to configured URLs with event data.

## Features

- **Event-Based**: Trigger webhooks on specific CMS events
- **Queue-Based Delivery**: Asynchronous processing for better performance
- **Automatic Retry**: Exponential backoff retry logic for failed deliveries
- **HMAC Signature Verification**: Secure webhook payload verification
- **Detailed Logging**: Complete delivery history with logs
- **Custom Headers**: Add custom HTTP headers to webhook requests
- **Event Filtering**: Subscribe only to events you care about
- **Test Webhooks**: Test webhook endpoints without waiting for events

## Available Events

### Post Events
- `post.created` - When a new post is created
- `post.updated` - When a post is updated
- `post.published` - When a post is published
- `post.deleted` - When a post is deleted
- `post.scheduled` - When a post is scheduled for publishing

### User Events
- `user.created` - When a new user is created
- `user.updated` - When a user is updated
- `user.deleted` - When a user is deleted
- `user.login` - When a user logs in
- `user.logout` - When a user logs out

### Comment Events
- `comment.created` - When a new comment is created
- `comment.updated` - When a comment is updated
- `comment.deleted` - When a comment is deleted
- `comment.approved` - When a comment is approved

### Category Events
- `category.created` - When a new category is created
- `category.updated` - When a category is updated
- `category.deleted` - When a category is deleted

### Tag Events
- `tag.created` - When a new tag is created
- `tag.updated` - When a tag is updated
- `tag.deleted` - When a tag is deleted

### Media Events
- `media.uploaded` - When media is uploaded
- `media.deleted` - When media is deleted

### Form Events
- `form.submitted` - When a form is submitted

### Newsletter Events
- `newsletter.subscribed` - When someone subscribes to newsletter
- `newsletter.unsubscribed` - When someone unsubscribes from newsletter
- `newsletter.sent` - When a newsletter campaign is sent

### System Events
- `system.backup_completed` - When a system backup is completed
- `system.error` - When a system error occurs

## Webhook Payload Structure

All webhook payloads follow this structure:

```json
{
  "timestamp": "2026-01-21T10:30:00Z",
  "delivery_id": "uuid-v4",
  "event": "post.created",
  "data": {
    // Event-specific data
  }
}
```

### Example Payloads

#### Post Created
```json
{
  "timestamp": "2026-01-21T10:30:00Z",
  "delivery_id": "550e8400-e29b-41d4-a716-446655440000",
  "event": "post.created",
  "data": {
    "id": 123,
    "title": "My New Post",
    "slug": "my-new-post",
    "excerpt": "This is an excerpt",
    "content": "Full post content here",
    "status": "published",
    "published_at": "2026-01-21T10:30:00Z",
    "author": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "categories": [
      {
        "id": 5,
        "name": "Technology",
        "slug": "technology"
      }
    ],
    "tags": [
      {
        "id": 10,
        "name": "Laravel",
        "slug": "laravel"
      }
    ],
    "url": "https://example.com/blog/my-new-post",
    "created_at": "2026-01-21T10:30:00Z"
  }
}
```

#### User Created
```json
{
  "timestamp": "2026-01-21T10:30:00Z",
  "delivery_id": "550e8400-e29b-41d4-a716-446655440000",
  "event": "user.created",
  "data": {
    "id": 456,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "role": "author",
    "email_verified": true,
    "created_at": "2026-01-21T10:30:00Z"
  }
}
```

## Webhook Headers

Every webhook request includes these headers:

- `Content-Type: application/json`
- `User-Agent: XQUANTORIA-Webhook/1.0`
- `X-Webhook-Event: <event_name>`
- `X-Webhook-Delivery: <delivery_id>`
- `X-Webhook-Timestamp: <ISO8601_timestamp>`

If a secret is configured, it also includes:

- `X-Webhook-Signature: sha256=<hmac_signature>`

## Signature Verification

To verify webhook authenticity:

```php
function verifyWebhook($payload, $signature, $secret) {
    $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($expectedSignature, $signature);
}

// Get the signature from header
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$payload = file_get_contents('php://input');

if (verifyWebhook($payload, $signature, 'your-webhook-secret')) {
    // Webhook is verified
    $data = json_decode($payload, true);
    // Process the webhook
} else {
    // Invalid signature
    http_response_code(403);
    echo 'Invalid signature';
}
```

## Retry Logic

Webhooks are automatically retried on failure with exponential backoff:

- **Attempt 1**: Immediate
- **Attempt 2**: 60 seconds delay
- **Attempt 3**: 300 seconds delay (5 minutes)
- **Maximum Attempts**: 3 (configurable)

A webhook is considered failed if:
- HTTP status code < 200 or >= 300
- Network timeout (30 seconds default)
- Connection error

## API Endpoints

### Webhook Management

#### List Webhooks
```
GET /api/v1/webhooks
Authorization: Bearer {token}
```

#### Create Webhook
```
POST /api/v1/webhooks
Authorization: Bearer {token}

{
  "name": "My Webhook",
  "url": "https://example.com/webhook",
  "events": ["post.created", "post.published"],
  "secret": "optional-secret",
  "headers": {
    "Authorization": "Bearer api-key"
  },
  "is_active": true
}
```

#### Get Webhook
```
GET /api/v1/webhooks/{id}
Authorization: Bearer {token}
```

#### Update Webhook
```
PUT /api/v1/webhooks/{id}
Authorization: Bearer {token}

{
  "name": "Updated Webhook Name",
  "url": "https://example.com/new-webhook",
  "events": ["post.created", "post.updated"],
  "is_active": false
}
```

#### Delete Webhook
```
DELETE /api/v1/webhooks/{id}
Authorization: Bearer {token}
```

#### Test Webhook
```
POST /api/v1/webhooks/{id}/test
Authorization: Bearer {token}
```

#### Toggle Webhook Status
```
POST /api/v1/webhooks/{id}/toggle
Authorization: Bearer {token}
```

#### Retry Failed Webhooks
```
POST /api/v1/webhooks/{id}/retry
Authorization: Bearer {token}

{
  "limit": 10
}
```

#### Regenerate Secret
```
POST /api/v1/webhooks/{id}/regenerate-secret
Authorization: Bearer {token}
```

### Webhook Logs

#### Get Webhook Logs
```
GET /api/v1/webhooks/{id}/logs?status=failed&per_page=50
Authorization: Bearer {token}
```

Query Parameters:
- `status`: success, failed (optional)
- `event_type`: Filter by event type (optional)
- `per_page`: Number of results per page (default: 50)

#### Get Single Log Entry
```
GET /api/v1/webhooks/{id}/logs/{log_id}
Authorization: Bearer {token}
```

### Webhook Statistics

```
GET /api/v1/webhooks/{id}/stats
Authorization: Bearer {token}
```

Returns:
- Total deliveries
- Success rate
- Successful/Failed counts
- Average duration
- Last successful/failed delivery timestamps

## Configuration

Configuration is in `config/webhooks.php`:

```php
return [
    // Maximum retry attempts
    'max_attempts' => env('WEBHOOK_MAX_ATTEMPTS', 3),

    // Request timeout in seconds
    'timeout' => env('WEBHOOK_TIMEOUT', 30),

    // Initial retry delay in seconds
    'retry_delay' => env('WEBHOOK_RETRY_DELAY', 60),

    // Signature algorithm
    'signature_algorithm' => env('WEBHOOK_SIGNATURE_ALGORITHM', 'sha256'),

    // Signature header name
    'signature_header' => env('WEBHOOK_SIGNATURE_HEADER', 'X-Webhook-Signature'),

    // Queue configuration
    'queue' => [
        'connection' => env('WEBHOOK_QUEUE_CONNECTION', config('queue.default')),
        'queue' => env('WEBHOOK_QUEUE_NAME', 'webhooks'),
    ],
];
```

## Best Practices

### 1. Security
- Always use HTTPS for webhook URLs
- Set a strong secret for signature verification
- Verify signatures on your endpoint
- Return appropriate HTTP status codes

### 2. Endpoint Implementation
- Return 2xx status codes quickly (< 5 seconds)
- Process heavy tasks asynchronously
- Handle duplicate deliveries (idempotency)
- Log all received webhooks

### 3. Performance
- Subscribe only to necessary events
- Use efficient processing in your endpoint
- Monitor webhook delivery rates
- Clean up old logs regularly

### 4. Error Handling
- Implement proper error logging
- Monitor failed deliveries
- Set up alerts for repeated failures
- Use retry functionality for transient failures

## Troubleshooting

### Webhook Not Firing

1. Check webhook is active
2. Verify event is selected in webhook configuration
3. Check event is actually occurring
4. Review application logs

### Delivery Failures

1. Check webhook URL is accessible
2. Verify endpoint returns 2xx status
3. Review webhook logs for error details
4. Test webhook endpoint manually
5. Check network connectivity

### Signature Verification Failing

1. Ensure secret matches on both ends
2. Check signature calculation algorithm
3. Verify raw payload is used (not decoded)
4. Check for encoding issues

## Frontend Usage

Navigate to **Settings > Webhooks** in the admin panel to:

1. Create new webhooks
2. Configure event subscriptions
3. Test webhook endpoints
4. View delivery logs
5. Monitor webhook statistics
6. Retry failed deliveries
7. Regenerate secrets

## Database Schema

### webhooks Table
- `id` - Primary key
- `name` - Webhook name
- `url` - Endpoint URL
- `events` - JSON array of subscribed events
- `secret` - HMAC secret
- `headers` - JSON object of custom headers
- `is_active` - Active status
- `user_id` - Creator user ID
- `last_triggered_at` - Last delivery timestamp
- `success_count` - Successful delivery count
- `failure_count` - Failed delivery count
- `created_at`, `updated_at` - Timestamps

### webhook_logs Table
- `id` - Primary key
- `webhook_id` - Foreign key to webhooks
- `event_type` - Event name
- `payload` - JSON payload
- `response_body` - Response text
- `status_code` - HTTP status code
- `attempt` - Delivery attempt number
- `success` - Success status
- `error_message` - Error text
- `duration` - Request duration in ms
- `headers` - Request headers
- `delivered_at` - Delivery timestamp
- `created_at`, `updated_at` - Timestamps

## Queue Worker

Ensure your queue worker is running to process webhook deliveries:

```bash
php artisan queue:work --queue=webhooks --tries=3
```

For production with supervisor, see Laravel Queue documentation.
