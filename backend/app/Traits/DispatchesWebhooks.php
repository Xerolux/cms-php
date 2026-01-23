<?php

namespace App\Traits;

use App\Services\WebhookService;
use Illuminate\Support\Facades\App;

trait DispatchesWebhooks
{
    /**
     * Dispatch webhook events for model changes.
     */
    protected function dispatchWebhookEvent(string $event, array $payload = []): void
    {
        if (App::runningInConsole() && !App::runningUnitTests()) {
            return;
        }

        try {
            $webhookService = app(WebhookService::class);
            $webhookService->dispatch($event, $payload);
        } catch (\Exception $e) {
            // Log error but don't break the application flow
            logger()->error('Failed to dispatch webhook event', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
