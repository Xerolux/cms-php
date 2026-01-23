<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook_id',
        'event_type',
        'payload',
        'response_body',
        'status_code',
        'attempt',
        'success',
        'error_message',
        'duration',
        'headers',
        'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'success' => 'boolean',
        'duration' => 'integer',
        'delivered_at' => 'datetime',
    ];

    /**
     * Get the webhook that owns the log.
     */
    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }

    /**
     * Scope to only successful deliveries.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * Scope to only failed deliveries.
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope to only logs for specific event.
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->where('event_type', $event);
    }

    /**
     * Scope to only retryable failed logs.
     */
    public function scopeRetryable($query)
    {
        return $query->failed()
            ->where('attempt', '<', config('webhooks.max_attempts', 3));
    }
}
