<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSent extends Model
{
    use HasFactory;

    protected $fillable = [
        'newsletter_id',
        'subscriber_id',
        'sent_at',
        'opened_at',
        'clicked_at',
        'unsubscribe_token',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    public function newsletter()
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function subscriber()
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }

    public function scopeOpened($query)
    {
        return $query->whereNotNull('opened_at');
    }

    public function scopeClicked($query)
    {
        return $query->whereNotNull('clicked_at');
    }

    public function markAsOpened()
    {
        if (!$this->opened_at) {
            $this->update([
                'opened_at' => now(),
            ]);

            $this->subscriber->incrementOpened();
            $this->newsletter->increment('opened_count');
        }
    }

    public function markAsClicked()
    {
        if (!$this->clicked_at) {
            $this->update([
                'clicked_at' => now(),
            ]);

            $this->subscriber->incrementClicked();
            $this->newsletter->increment('clicked_count');
        }
    }
}
