<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'status',
        'confirmation_token',
        'confirmed_at',
        'unsubscribed_at',
        'unsubscribe_token',
        'emails_sent',
        'emails_opened',
        'emails_clicked',
        'user_id',
        'ip_address',
        'referrer',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    protected $hidden = [
        'confirmation_token',
        'unsubscribe_token',
    ];

    protected $appends = [
        'full_name',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sent()
    {
        return $this->hasMany(NewsletterSent::class, 'subscriber_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnsubscribed($query)
    {
        return $query->where('status', 'unsubscribed');
    }

    public function scopeBounced($query)
    {
        return $query->where('status', 'bounced');
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getConfirmUrlAttribute(): string
    {
        return url("/newsletter/confirm/{$this->confirmation_token}");
    }

    public function getUnsubscribeUrlAttribute(): string
    {
        return url("/newsletter/unsubscribe/{$this->unsubscribe_token}");
    }

    public function generateConfirmationToken()
    {
        $this->confirmation_token = Str::random(64);
        $this->save();
    }

    public function generateUnsubscribeToken()
    {
        $this->unsubscribe_token = Str::random(64);
        $this->save();
    }

    public function confirm()
    {
        $this->update([
            'status' => 'active',
            'confirmed_at' => now(),
            'confirmation_token' => null,
        ]);
    }

    public function unsubscribe()
    {
        $this->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);
    }

    public function markAsBounced()
    {
        $this->update([
            'status' => 'bounced',
        ]);
    }

    public function incrementSent()
    {
        $this->increment('emails_sent');
    }

    public function incrementOpened()
    {
        $this->increment('emails_opened');
    }

    public function incrementClicked()
    {
        $this->increment('emails_clicked');
    }

    public function getEngagementRateAttribute(): float
    {
        if ($this->emails_sent === 0) {
            return 0;
        }

        $totalEngagement = $this->emails_opened + $this->emails_clicked;
        return round(($totalEngagement / ($this->emails_sent * 2)) * 100, 2);
    }
}
