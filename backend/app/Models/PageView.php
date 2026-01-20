<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageView extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'page_url',
        'ip_address',
        'user_agent',
        'referer',
        'country_code',
        'device_type',
        'browser',
        'user_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope für Statistiken
    public function scopeForPeriod($query, $period)
    {
        return $query->where('viewed_at', '>=', now()->sub($period));
    }

    public function scopeToday($query)
    {
        return $query->whereDate('viewed_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('viewed_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereYear('viewed_at', now()->year)
            ->whereMonth('viewed_at', now()->month);
    }

    // Device Type Detection
    public static function detectDeviceType(string $userAgent): string
    {
        $mobileAgents = ['Android', 'iPhone', 'iPad', 'Mobile', 'BlackBerry', 'Opera Mini'];

        foreach ($mobileAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                return stripos($userAgent, 'iPad') !== false ? 'tablet' : 'mobile';
            }
        }

        return 'desktop';
    }

    // Browser Detection
    public static function detectBrowser(string $userAgent): string
    {
        if (stripos($userAgent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (stripos($userAgent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (stripos($userAgent, 'Safari') !== false) {
            return 'Safari';
        } elseif (stripos($userAgent, 'Edge') !== false) {
            return 'Edge';
        } elseif (stripos($userAgent, 'Opera') !== false) {
            return 'Opera';
        }

        return 'Other';
    }
}
