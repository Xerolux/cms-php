<?php

namespace App\Listeners\Webhook;

use App\Models\User;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;

class UserEventListener
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle user created event.
     */
    public function onUserCreated(User $user): void
    {
        try {
            $this->webhookService->dispatch('user.created', $this->transformUser($user));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch user.created webhook', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle user updated event.
     */
    public function onUserUpdated(User $user): void
    {
        try {
            $this->webhookService->dispatch('user.updated', $this->transformUser($user));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch user.updated webhook', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle user deleted event.
     */
    public function onUserDeleted(User $user): void
    {
        try {
            $this->webhookService->dispatch('user.deleted', [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch user.deleted webhook', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle user login event.
     */
    public function onUserLogin(array $data): void
    {
        try {
            $this->webhookService->dispatch('user.login', [
                'user_id' => $data['user']->id,
                'name' => $data['user']->name,
                'email' => $data['user']->email,
                'ip_address' => $data['ip_address'] ?? null,
                'user_agent' => $data['user_agent'] ?? null,
                'logged_in_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch user.login webhook', [
                'user_id' => $data['user']->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle user logout event.
     */
    public function onUserLogout(array $data): void
    {
        try {
            $this->webhookService->dispatch('user.logout', [
                'user_id' => $data['user']->id,
                'name' => $data['user']->name,
                'email' => $data['user']->email,
                'logged_out_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch user.logout webhook', [
                'user_id' => $data['user']->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Transform user for webhook payload.
     */
    protected function transformUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? null,
            'is_super_admin' => $user->is_super_admin ?? false,
            'email_verified' => $user->hasVerifiedEmail(),
            'two_factor_enabled' => !empty($user->two_factor_secret),
            'created_at' => $user->created_at->toIso8601String(),
            'updated_at' => $user->updated_at->toIso8601String(),
        ];
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            'user.created' => 'onUserCreated',
            'user.updated' => 'onUserUpdated',
            'user.deleted' => 'onUserDeleted',
            'user.login' => 'onUserLogin',
            'user.logout' => 'onUserLogout',
        ];
    }
}
