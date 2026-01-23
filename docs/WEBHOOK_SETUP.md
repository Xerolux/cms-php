# Webhook System Setup Guide

## Installation Steps

### 1. Run Migrations

Run the database migrations to create the webhook tables:

```bash
cd backend
php artisan migrate
```

This will create:
- `webhooks` table - Stores webhook configurations
- `webhook_logs` table - Stores webhook delivery logs

### 2. Publish Configuration (Optional)

If you want to customize the webhook configuration:

```bash
php artisan vendor:publish --tag=webhook-config
```

Configuration file: `config/webhooks.php`

### 3. Configure Queue

Webhooks use Laravel's queue system. Ensure you have a queue driver configured in `.env`:

```env
QUEUE_CONNECTION=database
```

Or for Redis (recommended for production):
```env
QUEUE_CONNECTION=redis
```

### 4. Run Queue Worker

Start the queue worker to process webhook deliveries:

```bash
php artisan queue:work --queue=webhooks --tries=3 --timeout=30
```

For production, use Supervisor to keep the worker running.

### 5. Configure Webhooks in UI

1. Login to XQUANTORIA admin panel
2. Navigate to **Webhooks** section
3. Click **Create Webhook**
4. Fill in:
   - **Name**: Descriptive name (e.g., "Slack Notifications")
   - **URL**: Your endpoint URL (e.g., "https://hooks.slack.com/services/...")
   - **Events**: Select events to subscribe to
   - **Secret**: Optional secret for signature verification
   - **Headers**: Optional custom headers
   - **Active**: Enable/disable the webhook
5. Click **Save**

### 6. Test Webhook

After creating a webhook:
1. Click the **Test** button
2. Check if test event was delivered successfully
3. View logs to see delivery details

## Integration Examples

### Example 1: Slack Notification on Post Published

Create a webhook with:
- **URL**: Your Slack Incoming Webhook URL
- **Events**: `post.published`
- **Secret**: (optional)

Your Slack endpoint will receive:

```json
{
  "timestamp": "2026-01-21T10:30:00Z",
  "delivery_id": "uuid",
  "event": "post.published",
  "data": {
    "id": 123,
    "title": "New Blog Post",
    "author": "John Doe",
    "url": "https://example.com/blog/new-post"
  }
}
```

### Example 2: Discord Notification

Create a Discord webhook integration:

```php
// Your endpoint code
$payload = json_decode(file_get_contents('php://input'), true);

if ($payload['event'] === 'post.published') {
    $post = $payload['data'];

    $message = [
        'content' => "📝 New post published!",
        'embeds' => [
            [
                'title' => $post['title'],
                'url' => $post['url'],
                'author' => [
                    'name' => $post['author']['name']
                ],
                'description' => $post['excerpt']
            ]
        ]
    ];

    // Send to Discord
    // ...
}
```

### Example 3: Custom Analytics Service

```php
// Verify webhook
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
$payload = file_get_contents('php://input');

if ($webhookService->verifySignature($payload, $signature, 'your-secret')) {
    $data = json_decode($payload, true);

    // Track in your analytics
    if ($data['event'] === 'post.created') {
        analytics::track('Post Created', [
            'post_id' => $data['data']['id'],
            'author' => $data['data']['author']['name'],
            'timestamp' => $data['timestamp']
        ]);
    }

    http_response_code(200);
    echo 'OK';
} else {
    http_response_code(403);
    echo 'Invalid signature';
}
```

## Production Setup

### Supervisor Configuration

Create `/etc/supervisor/conf.d/xquantoria-webhooks.conf`:

```ini
[program:xquantoria-webhooks]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/xquantoria/backend/artisan queue:work --queue=webhooks --tries=3 --timeout=30
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/xquantoria/backend/storage/logs/webhook-worker.log
stopwaitsecs=3600
```

Restart supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start xquantoria-webhooks:*
```

### Monitoring

Monitor webhook queue:
```bash
php artisan queue:monitor
```

Check failed webhook jobs:
```bash
php artisan queue:failed
```

Retry all failed webhooks:
```bash
php artisan queue:retry all
```

## Troubleshooting

### Webhooks Not Being Delivered

1. **Check Queue Worker**
   ```bash
   php artisan queue:work --queue=webhooks --verbose
   ```

2. **Check Configuration**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

3. **Review Logs**
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **Test Queue**
   ```bash
   php artisan queue:test
   ```

### High Failure Rate

1. Check webhook endpoint is accessible
2. Verify endpoint returns 2xx status quickly
3. Increase timeout in configuration
4. Check rate limiting on receiving server

### Performance Issues

1. Use Redis for queue driver instead of database
2. Increase number of queue workers
3. Implement caching in webhook endpoints
4. Clean up old logs regularly

## Maintenance

### Clean Old Logs

Automatically clean logs older than 30 days:

```bash
php artisan tinker
>>> app('webhook.service')->cleanOldLogs(30);
```

Or add to scheduled task in `app/Console/Kernel.php`:

```php
$schedule->call(function () {
    app('webhook.service')->cleanOldLogs(30);
})->weekly();
```

### Monitor Statistics

Regular monitoring via API:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
  https://your-domain.com/api/v1/webhooks/1/stats
```

## Environment Variables

Optional environment variables for `.env`:

```env
# Webhook Configuration
WEBHOOK_MAX_ATTEMPTS=3
WEBHOOK_TIMEOUT=30
WEBHOOK_RETRY_DELAY=60
WEBHOOK_SIGNATURE_ALGORITHM=sha256
WEBHOOK_SIGNATURE_HEADER=X-Webhook-Signature
WEBHOOK_TIMESTAMP_HEADER=X-Webhook-Timestamp
WEBHOOK_EVENT_HEADER=X-Webhook-Event
WEBHOOK_DELIVERY_HEADER=X-Webhook-Delivery

# Queue Configuration
WEBHOOK_QUEUE_CONNECTION=redis
WEBHOOK_QUEUE_NAME=webhooks
```

## Security Best Practices

1. **Always use HTTPS** for webhook URLs
2. **Set strong secrets** for signature verification
3. **Verify signatures** on receiving end
4. **Implement rate limiting** on your endpoints
5. **Use authentication headers** for external APIs
6. **Never expose secrets** in logs
7. **Rotate secrets regularly**
8. **Monitor for suspicious activity**

## Support

For issues or questions:
- Check documentation: `docs/WEBHOOK_SYSTEM.md`
- Review logs: `storage/logs/laravel.log`
- Test with webhook test sites: https://webhook.site
