<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use HasDatabase, HasDomains;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'plan',
        'settings',
        'is_active',
        'trial_ends_at',
        'subscription_ends_at',
        'max_users',
        'max_storage_gb',
        'max_posts',
        'features',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    /**
     * Get the plan details for the tenant.
     */
    public function getPlanDetailsAttribute(): array
    {
        return $this->getPlanConfig($this->plan);
    }

    /**
     * Get configuration for a specific plan.
     */
    public function getPlanConfig(string $plan): array
    {
        return config('tenancy.plans.' . $plan, config('tenancy.plans.free'));
    }

    /**
     * Check if tenant has a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        $planDetails = $this->getPlanDetailsAttribute();

        return in_array($feature, $planDetails['features'] ?? []);
    }

    /**
     * Check if tenant can add more users.
     */
    public function canAddUser(): bool
    {
        $maxUsers = $this->max_users ?? $this->getPlanDetailsAttribute()['max_users'] ?? 5;

        $userCount = \App\Models\User::count();

        return $userCount < $maxUsers;
    }

    /**
     * Check if tenant can add more posts.
     */
    public function canAddPost(): bool
    {
        $maxPosts = $this->max_posts ?? $this->getPlanDetailsAttribute()['max_posts'] ?? 50;

        $postCount = \App\Models\Post::count();

        return $postCount < $maxPosts;
    }

    /**
     * Get current storage usage in GB.
     */
    public function getStorageUsageAttribute(): float
    {
        $totalSize = 0;

        $mediaPath = storage_path('app/public/' . $this->id);
        if (is_dir($mediaPath)) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($mediaPath)) as $file) {
                $totalSize += $file->getSize();
            }
        }

        return round($totalSize / 1024 / 1024 / 1024, 2); // Convert to GB
    }

    /**
     * Check if tenant has exceeded storage limit.
     */
    public function hasExceededStorageLimit(): bool
    {
        $maxStorage = $this->max_storage_gb ?? $this->getPlanDetailsAttribute()['max_storage_gb'] ?? 10;

        return $this->storage_usage >= $maxStorage;
    }

    /**
     * Check if tenant is on trial.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if tenant subscription is active.
     */
    public function hasActiveSubscription(): bool
    {
        if ($this->isOnTrial()) {
            return true;
        }

        return $this->is_active && (!$this->subscription_ends_at || $this->subscription_ends_at->isFuture());
    }

    /**
     * Get days remaining in trial or subscription.
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if ($this->isOnTrial()) {
            return now()->diffInDays($this->trial_ends_at);
        }

        if ($this->subscription_ends_at) {
            return now()->diffInDays($this->subscription_ends_at);
        }

        return null;
    }

    /**
     * Scope to filter by active tenants.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('subscription_ends_at')
                    ->orWhere('subscription_ends_at', '>', now());
            });
    }

    /**
     * Scope to filter by plan.
     */
    public function scopeWithPlan($query, string $plan)
    {
        return $query->where('plan', $plan);
    }

    /**
     * Get tenant statistics.
     */
    public function getStatisticsAttribute(): array
    {
        return [
            'users_count' => \App\Models\User::count(),
            'posts_count' => \App\Models\Post::count(),
            'pages_count' => \App\Models\Page::count(),
            'media_count' => \App\Models\Media::count(),
            'storage_usage' => $this->storage_usage,
            'storage_limit' => $this->max_storage_gb ?? $this->getPlanDetailsAttribute()['max_storage_gb'] ?? 10,
            'storage_percentage' => round(
                ($this->storage_usage / ($this->max_storage_gb ?? $this->getPlanDetailsAttribute()['max_storage_gb'] ?? 10)) * 100,
                2
            ),
            'days_remaining' => $this->days_remaining,
            'is_on_trial' => $this->isOnTrial(),
            'has_active_subscription' => $this->hasActiveSubscription(),
        ];
    }
}
