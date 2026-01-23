<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'events',
        'secret',
        'headers',
        'is_active',
        'user_id',
        'last_triggered_at',
        'success_count',
        'failure_count',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'success_count' => 'integer',
        'failure_count' => 'integer',
    ];

    protected $hidden = [
        'secret',
    ];

    /**
     * Get the user that owns the webhook.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the logs for the webhook.
     */
    public function logs()
    {
        return $this->hasMany(WebhookLog::class)->latest();
    }

    /**
     * Get successful logs for the webhook.
     */
    public function successfulLogs()
    {
        return $this->logs()->where('status_code', '>=', 200)->where('status_code', '<', 300);
    }

    /**
     * Get failed logs for the webhook.
     */
    public function failedLogs()
    {
        return $this->logs()->where(function ($query) {
            $query->where('status_code', '<', 200)
                ->orWhere('status_code', '>=', 300)
                ->orWhereNull('status_code');
        });
    }

    /**
     * Check if webhook should fire for given event.
     */
    public function shouldFireForEvent(string $event): bool
    {
        return $this->is_active && in_array($event, $this->events ?? []);
    }

    /**
     * Increment success count.
     */
    public function incrementSuccess(): void
    {
        $this->increment('success_count');
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Increment failure count.
     */
    public function incrementFailure(): void
    {
        $this->increment('failure_count');
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Get success rate percentage.
     */
    public function getSuccessRateAttribute(): float
    {
        $total = $this->success_count + $this->failure_count;
        if ($total === 0) {
            return 0.0;
        }
        return round(($this->success_count / $total) * 100, 2);
    }

    /**
     * Scope to only active webhooks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only webhooks for specific event.
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }
}
