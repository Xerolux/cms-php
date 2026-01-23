<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\WebhookService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public $backoff = [60, 300, 900];

    /**
     * Delete the job if its models no longer exist.
     */
    public $deleteWhenMissingModels = true;

    protected int $webhookId;
    protected string $eventType;
    protected array $payload;
    protected string $deliveryId;
    protected int $attempt;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $webhookId,
        string $eventType,
        array $payload,
        string $deliveryId,
        int $attempt = 1
    ) {
        $this->webhookId = $webhookId;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->deliveryId = $deliveryId;
        $this->attempt = $attempt;

        $this->onQueue(config('webhooks.queue.queue', 'webhooks'));
    }

    /**
     * Execute the job.
     */
    public function handle(WebhookService $webhookService): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (!$webhook || !$webhook->is_active) {
            Log::info('Webhook not found or inactive', [
                'webhook_id' => $this->webhookId,
                'event' => $this->eventType,
            ]);
            return;
        }

        if (!$webhook->shouldFireForEvent($this->eventType)) {
            Log::info('Webhook should not fire for event', [
                'webhook_id' => $this->webhookId,
                'event' => $this->eventType,
            ]);
            return;
        }

        $startTime = microtime(true);

        try {
            $this->sendWebhook($webhook, $webhookService);
        } catch (Exception $e) {
            $this->handleFailure($webhook, $e, $startTime);
        }
    }

    /**
     * Send the webhook HTTP request.
     */
    protected function sendWebhook(Webhook $webhook, WebhookService $webhookService): void
    {
        $payloadJson = json_encode($this->payload);
        $startTime = microtime(true);

        // Build headers
        $headers = array_merge(
            [
                'Content-Type' => 'application/json',
                'User-Agent' => 'XQUANTORIA-Webhook/1.0',
                config('webhooks.event_header', 'X-Webhook-Event') => $this->eventType,
                config('webhooks.delivery_header', 'X-Webhook-Delivery') => $this->deliveryId,
                config('webhooks.timestamp_header', 'X-Webhook-Timestamp') => now()->toIso8601String(),
            ],
            $webhook->headers ?? []
        );

        // Add signature if secret is set
        if ($webhook->secret) {
            $signature = $webhookService->generateSignature($payloadJson, $webhook->secret);
            $headers[config('webhooks.signature_header', 'X-Webhook-Signature')] = $signature;
        }

        // Send HTTP request
        $response = Http::timeout(config('webhooks.timeout', 30))
            ->withHeaders($headers)
            ->post($webhook->url, $this->payload);

        $duration = round((microtime(true) - $startTime) * 1000);
        $statusCode = $response->status();
        $success = $statusCode >= 200 && $statusCode < 300;

        // Log the delivery attempt
        WebhookLog::create([
            'webhook_id' => $webhook->id,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'response_body' => mb_substr($response->body(), 0, 65535), // Limit TEXT field size
            'status_code' => $statusCode,
            'attempt' => $this->attempt,
            'success' => $success,
            'error_message' => !$success ? $response->body() : null,
            'duration' => $duration,
            'headers' => $headers,
            'delivered_at' => now(),
        ]);

        // Update webhook statistics
        if ($success) {
            $webhook->incrementSuccess();
        } else {
            $webhook->incrementFailure();
        }

        // Handle failed response
        if (!$success && $this->attempt < config('webhooks.max_attempts', 3)) {
            $this->release($this->getRetryDelay($this->attempt));
        }
    }

    /**
     * Handle webhook failure.
     */
    protected function handleFailure(Webhook $webhook, Exception $e, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000);

        WebhookLog::create([
            'webhook_id' => $webhook->id,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'response_body' => null,
            'status_code' => null,
            'attempt' => $this->attempt,
            'success' => false,
            'error_message' => $e->getMessage(),
            'duration' => $duration,
            'delivered_at' => now(),
        ]);

        $webhook->incrementFailure();

        Log::error('Webhook delivery failed', [
            'webhook_id' => $webhook->id,
            'event' => $this->eventType,
            'attempt' => $this->attempt,
            'error' => $e->getMessage(),
        ]);

        // Retry if attempts not exhausted
        if ($this->attempt < config('webhooks.max_attempts', 3)) {
            $this->release($this->getRetryDelay($this->attempt));
        }
    }

    /**
     * Calculate retry delay with exponential backoff.
     */
    protected function getRetryDelay(int $attempt): int
    {
        $baseDelay = config('webhooks.retry_delay', 60);
        return $baseDelay * pow(2, $attempt - 1);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Webhook job failed permanently', [
            'webhook_id' => $this->webhookId,
            'event' => $this->eventType,
            'attempt' => $this->attempt,
            'error' => $exception->getMessage(),
        ]);
    }
}
