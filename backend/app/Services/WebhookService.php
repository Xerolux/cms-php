<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Jobs\SendWebhookJob;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Dispatch webhooks for a specific event.
     *
     * @param string $event
     * @param array $payload
     * @return void
     */
    public function dispatch(string $event, array $payload = []): void
    {
        $webhooks = Webhook::active()->forEvent($event)->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        foreach ($webhooks as $webhook) {
            $this->dispatchWebhook($webhook, $event, $payload);
        }
    }

    /**
     * Dispatch a single webhook.
     *
     * @param Webhook $webhook
     * @param string $event
     * @param array $payload
     * @return void
     */
    protected function dispatchWebhook(Webhook $webhook, string $event, array $payload): void
    {
        $deliveryId = (string) Str::uuid();

        $enhancedPayload = $this->preparePayload($event, $payload, $deliveryId);

        SendWebhookJob::dispatch(
            $webhook->id,
            $event,
            $enhancedPayload,
            $deliveryId
        )->onConnection(config('webhooks.queue.connection'))
            ->onQueue(config('webhooks.queue.queue'));
    }

    /**
     * Prepare payload with additional metadata.
     *
     * @param string $event
     * @param array $payload
     * @param string $deliveryId
     * @return array
     */
    protected function preparePayload(string $event, array $payload, string $deliveryId): array
    {
        $enhanced = [];

        if (config('webhooks.payload.include_timestamp', true)) {
            $enhanced['timestamp'] = now()->toIso8601String();
        }

        if (config('webhooks.payload.include_delivery_id', true)) {
            $enhanced['delivery_id'] = $deliveryId;
        }

        if (config('webhooks.payload.include_event_type', true)) {
            $enhanced['event'] = $event;
        }

        return array_merge($enhanced, [
            'data' => $payload
        ]);
    }

    /**
     * Generate HMAC signature for webhook payload.
     *
     * @param string $payload
     * @param string $secret
     * @return string
     */
    public function generateSignature(string $payload, string $secret): string
    {
        $algorithm = config('webhooks.signature_algorithm', 'sha256');
        $hash = hash_hmac($algorithm, $payload, $secret);

        return $algorithm . '=' . $hash;
    }

    /**
     * Verify webhook signature.
     *
     * @param string $payload
     * @param string $signature
     * @param string $secret
     * @return bool
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expectedSignature = $this->generateSignature($payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Get available webhook events grouped by category.
     *
     * @return array
     */
    public function getAvailableEvents(): array
    {
        return config('webhooks.events', []);
    }

    /**
     * Get webhook event categories.
     *
     * @return array
     */
    public function getEventCategories(): array
    {
        return config('webhooks.categories', []);
    }

    /**
     * Get events grouped by category.
     *
     * @return array
     */
    public function getEventsGroupedByCategory(): array
    {
        $events = $this->getAvailableEvents();
        $grouped = [];

        foreach ($events as $key => $event) {
            $category = $event['category'] ?? 'Other';
            $grouped[$category][$key] = $event;
        }

        return $grouped;
    }

    /**
     * Validate webhook URL.
     *
     * @param string $url
     * @return bool
     */
    public function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https']);
    }

    /**
     * Generate webhook secret.
     *
     * @return string
     */
    public function generateSecret(): string
    {
        return Str::random(40);
    }

    /**
     * Retry failed webhook deliveries.
     *
     * @param Webhook $webhook
     * @param int|null $limit
     * @return int
     */
    public function retryFailedWebhooks(Webhook $webhook, ?int $limit = null): int
    {
        $query = WebhookLog::where('webhook_id', $webhook->id)
            ->retryable()
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        $logs = $query->get();
        $retryCount = 0;

        foreach ($logs as $log) {
            SendWebhookJob::dispatch(
                $webhook->id,
                $log->event_type,
                $log->payload,
                (string) Str::uuid(),
                $log->attempt + 1
            )->onConnection(config('webhooks.queue.connection'))
                ->onQueue(config('webhooks.queue.queue'));

            $retryCount++;
        }

        return $retryCount;
    }

    /**
     * Get webhook statistics.
     *
     * @param Webhook $webhook
     * @return array
     */
    public function getWebhookStats(Webhook $webhook): array
    {
        $totalDeliveries = $webhook->logs()->count();
        $successfulDeliveries = $webhook->logs()->successful()->count();
        $failedDeliveries = $webhook->logs()->failed()->count();

        $avgDuration = $webhook->logs()
            ->whereNotNull('duration')
            ->avg('duration') ?? 0;

        $lastSuccessfulDelivery = $webhook->logs()
            ->successful()
            ->latest('delivered_at')
            ->first();

        $lastFailedDelivery = $webhook->logs()
            ->failed()
            ->latest('created_at')
            ->first();

        return [
            'total_deliveries' => $totalDeliveries,
            'successful_deliveries' => $successfulDeliveries,
            'failed_deliveries' => $failedDeliveries,
            'success_rate' => $webhook->success_rate,
            'average_duration' => round($avgDuration, 2) . 'ms',
            'last_successful_delivery' => $lastSuccessfulDelivery?->delivered_at,
            'last_failed_delivery' => $lastFailedDelivery?->created_at,
        ];
    }

    /**
     * Test webhook delivery.
     *
     * @param Webhook $webhook
     * @return array
     */
    public function testWebhook(Webhook $webhook): array
    {
        $testEvent = 'webhook.test';
        $testPayload = [
            'test' => true,
            'timestamp' => now()->toIso8601String(),
            'message' => 'This is a test webhook delivery',
            'webhook_id' => $webhook->id,
            'webhook_name' => $webhook->name,
        ];

        $deliveryId = (string) Str::uuid();
        $enhancedPayload = $this->preparePayload($testEvent, $testPayload, $deliveryId);

        // Dispatch synchronously for testing
        $job = new SendWebhookJob(
            $webhook->id,
            $testEvent,
            $enhancedPayload,
            $deliveryId
        );

        $job->handle();

        $log = WebhookLog::where('webhook_id', $webhook->id)
            ->where('event_type', $testEvent)
            ->latest()
            ->first();

        return [
            'success' => $log?->success ?? false,
            'status_code' => $log?->status_code,
            'duration' => $log?->duration,
            'error_message' => $log?->error_message,
        ];
    }

    /**
     * Clean old webhook logs.
     *
     * @param int $days
     * @return int
     */
    public function cleanOldLogs(int $days = 30): int
    {
        return WebhookLog::where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
