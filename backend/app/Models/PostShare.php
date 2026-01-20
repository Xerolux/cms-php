<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'platform',
        'share_url',
        'qr_code',
        'clicks',
        'shared_by',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'clicks' => 'integer',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function sharedBy()
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function getQrCodeUrlAttribute(): string
    {
        if ($this->qr_code) {
            return Storage::url($this->qr_code);
        }
        return '';
    }

    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }
}
