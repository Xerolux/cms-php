<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GeoIpService
{
    /**
     * Get country code from IP address.
     */
    public function getCountryCode(?string $ip): ?string
    {
        if (!$ip || $ip === '127.0.0.1' || $ip === '::1') {
            return null;
        }

        return Cache::remember("geoip_country_{$ip}", 86400, function () use ($ip) {
            try {
                // Using ip-api.com (free for non-commercial use, 45 requests/minute)
                // In production with high traffic, you should use a local database or a paid service.
                $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=status,countryCode");

                if ($response->successful()) {
                    $data = $response->json();
                    if (($data['status'] ?? '') === 'success') {
                        return $data['countryCode'] ?? null;
                    }
                }
            } catch (\Exception $e) {
                // Fail silently to avoid breaking the request
                return null;
            }
            return null;
        });
    }
}
