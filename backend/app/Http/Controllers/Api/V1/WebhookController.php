<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Display a listing of webhooks.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Webhook::with(['user:id,name,email']);

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by event
        if ($request->has('event')) {
            $query->forEvent($request->input('event'));
        }

        // Search by name or URL
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        $webhooks = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $webhooks->items(),
            'meta' => [
                'current_page' => $webhooks->currentPage(),
                'last_page' => $webhooks->lastPage(),
                'per_page' => $webhooks->perPage(),
                'total' => $webhooks->total(),
            ],
        ]);
    }

    /**
     * Store a newly created webhook.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'events.*' => ['string', Rule::in(array_keys($this->webhookService->getAvailableEvents()))],
            'secret' => 'nullable|string|max:100',
            'headers' => 'nullable|array',
            'headers.*' => 'string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate URL
        if (!$this->webhookService->isValidUrl($request->input('url'))) {
            return response()->json([
                'message' => 'Invalid webhook URL',
            ], 422);
        }

        // Generate secret if not provided
        $secret = $request->input('secret') ?? $this->webhookService->generateSecret();

        $webhook = Webhook::create([
            'name' => $request->input('name'),
            'url' => $request->input('url'),
            'events' => $request->input('events'),
            'secret' => $secret,
            'headers' => $request->input('headers'),
            'is_active' => $request->input('is_active', true),
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Webhook created successfully',
            'data' => $webhook->load('user:id,name,email'),
            'secret' => $secret, // Only show secret once on creation
        ], 201);
    }

    /**
     * Display the specified webhook.
     */
    public function show(Webhook $webhook): JsonResponse
    {
        $webhook->load(['user:id,name,email']);

        $stats = $this->webhookService->getWebhookStats($webhook);

        return response()->json([
            'data' => $webhook,
            'stats' => $stats,
        ]);
    }

    /**
     * Update the specified webhook.
     */
    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'url' => 'url|max:500',
            'events' => 'array|min:1',
            'events.*' => ['string', Rule::in(array_keys($this->webhookService->getAvailableEvents()))],
            'secret' => 'nullable|string|max:100',
            'headers' => 'nullable|array',
            'headers.*' => 'string|max:255',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validate URL if provided
        if ($request->has('url') && !$this->webhookService->isValidUrl($request->input('url'))) {
            return response()->json([
                'message' => 'Invalid webhook URL',
            ], 422);
        }

        $webhook->update($request->only([
            'name',
            'url',
            'events',
            'headers',
            'is_active',
        ]));

        // Update secret if provided
        if ($request->has('secret')) {
            $webhook->update(['secret' => $request->input('secret')]);
        }

        return response()->json([
            'message' => 'Webhook updated successfully',
            'data' => $webhook->load('user:id,name,email'),
        ]);
    }

    /**
     * Remove the specified webhook.
     */
    public function destroy(Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json([
            'message' => 'Webhook deleted successfully',
        ]);
    }

    /**
     * Get available webhook events.
     */
    public function events(): JsonResponse
    {
        $events = $this->webhookService->getEventsGroupedByCategory();
        $categories = $this->webhookService->getEventCategories();

        return response()->json([
            'events' => $events,
            'categories' => $categories,
        ]);
    }

    /**
     * Test a webhook.
     */
    public function test(Webhook $webhook): JsonResponse
    {
        $result = $this->webhookService->testWebhook($webhook);

        return response()->json([
            'message' => $result['success'] ? 'Webhook test successful' : 'Webhook test failed',
            'data' => $result,
        ]);
    }

    /**
     * Get webhook logs.
     */
    public function logs(Request $request, Webhook $webhook): JsonResponse
    {
        $query = $webhook->logs();

        // Filter by status
        if ($request->has('status')) {
            if ($request->input('status') === 'success') {
                $query->successful();
            } elseif ($request->input('status') === 'failed') {
                $query->failed();
            }
        }

        // Filter by event type
        if ($request->has('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        $logs = $query->latest()
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Get a specific log entry.
     */
    public function log(Webhook $webhook, WebhookLog $log): JsonResponse
    {
        if ($log->webhook_id !== $webhook->id) {
            return response()->json([
                'message' => 'Log not found for this webhook',
            ], 404);
        }

        return response()->json([
            'data' => $log,
        ]);
    }

    /**
     * Retry failed webhooks.
     */
    public function retry(Request $request, Webhook $webhook): JsonResponse
    {
        $limit = $request->input('limit', 10);
        $retryCount = $this->webhookService->retryFailedWebhooks($webhook, $limit);

        return response()->json([
            'message' => "Queued {$retryCount} failed webhooks for retry",
            'data' => [
                'retry_count' => $retryCount,
            ],
        ]);
    }

    /**
     * Regenerate webhook secret.
     */
    public function regenerateSecret(Webhook $webhook): JsonResponse
    {
        $newSecret = $this->webhookService->generateSecret();
        $webhook->update(['secret' => $newSecret]);

        return response()->json([
            'message' => 'Webhook secret regenerated successfully',
            'data' => [
                'secret' => $newSecret,
            ],
        ]);
    }

    /**
     * Get webhook statistics.
     */
    public function stats(Webhook $webhook): JsonResponse
    {
        $stats = $this->webhookService->getWebhookStats($webhook);

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Toggle webhook active status.
     */
    public function toggle(Webhook $webhook): JsonResponse
    {
        $webhook->update([
            'is_active' => !$webhook->is_active,
        ]);

        return response()->json([
            'message' => 'Webhook status updated',
            'data' => $webhook,
        ]);
    }
}
