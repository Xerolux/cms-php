<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait Cacheable
{
    /**
     * Caches eine Datenbankabfrage
     */
    protected function cacheQuery(string $key, callable $callback, int $ttl = 3600): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Cached ein Model
     */
    protected function cacheModel(string $key, callable $callback, int $ttl = 3600): mixed
    {
        return Cache::remember("model:{$key}", $ttl, $callback);
    }

    /**
     * Cleared cache für einen bestimmten Key
     */
    protected function clearCache(string $pattern = null): void
    {
        if ($pattern) {
            $redis = Cache::getRedis();
            $keys = $redis->keys("*{$pattern}*");

            if (!empty($keys)) {
                $redis->del($keys);
            }
        } else {
            Cache::flush();
        }
    }

    /**
     * Generiert Cache Key
     */
    protected function getCacheKey(...$parts): string
    {
        return implode(':', $parts);
    }
}
