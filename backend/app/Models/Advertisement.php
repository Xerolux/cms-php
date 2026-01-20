<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'zone',
        'ad_type',
        'content',
        'image_url',
        'link_url',
        'impressions',
        'clicks',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'impressions' => 'integer',
        'clicks' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    public function incrementImpressions()
    {
        $this->increment('impressions');
    }

    public function incrementClicks()
    {
        $this->increment('clicks');
    }

    public function getClickThroughRateAttribute()
    {
        if ($this->impressions === 0) {
            return 0;
        }

        return round(($this->clicks / $this->impressions) * 100, 2);
    }
}
