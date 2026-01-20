<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Newsletter extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'preview_text',
        'content',
        'status',
        'scheduled_at',
        'sent_at',
        'recipients_count',
        'opened_count',
        'clicked_count',
        'unsubscribed_count',
        'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sent()
    {
        return $this->hasMany(NewsletterSent::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function getOpenRateAttribute(): float
    {
        if ($this->recipients_count === 0) {
            return 0;
        }

        return round(($this->opened_count / $this->recipients_count) * 100, 2);
    }

    public function getClickRateAttribute(): float
    {
        if ($this->recipients_count === 0) {
            return 0;
        }

        return round(($this->clicked_count / $this->recipients_count) * 100, 2);
    }

    public function getUnsubscribeRateAttribute(): float
    {
        if ($this->recipients_count === 0) {
            return 0;
        }

        return round(($this->unsubscribed_count / $this->recipients_count) * 100, 2);
    }

    public function markAsSent()
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsScheduled()
    {
        $this->update([
            'status' => 'scheduled',
        ]);
    }
}
