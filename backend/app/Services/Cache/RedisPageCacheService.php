<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Redis Full-Page Caching Service
 *
 * Features:
 * - Cache entire HTTP responses
 * - Cache tags per resource
 * - Automatic cache invalidation on updates
 * - Compression for large responses
 * - Cache statistics and monitoring
 */
class RedisPageCacheService
{
    private string $prefix = 'page_cache:';
    private int $defaultTtl = 3600; // 1 hour
    private bool $compressionEnabled = true;
    private int $compressionThreshold = 10240; // 10KB

    /**
     * Cache a complete HTTP response
     */
    public function cacheResponse(
        string $key,
        Response $response,
        array $tags = [],
        ?int $ttl = null
    ): void {
        $cacheKey = $this->getCacheKey($key);
        $ttl = $ttl ?? $this->defaultTtl;

        $content = $response->content();
        $data = [
            'content' => $this->maybeCompress($content),
            'compressed' => $this->shouldCompress($content),
            'status' => $response->getStatusCode(),
            'headers' => $this->getCacheableHeaders($response->headers->all()),
            'tags' => $tags,
            'cached_at' => Carbon::now()->toIso8601String(),
            'ttl' => $ttl,
        ];

        // Store the response
        Redis::setex($cacheKey, $ttl, json_encode($data));

        // Add to tag sets for invalidation
        foreach ($tags as $tag) {
            $this->addKeyToTag($tag, $cacheKey, $ttl);
        }

        // Track cache statistics
        $this->trackCache('set', $key, strlen($content));
    }

    /**
     * Get cached response if available
     */
    public function getCachedResponse(string $key): ?array
    {
        $cacheKey = $this->getCacheKey($key);

        $cached = Redis::get($cacheKey);
        if (!$cached) {
            $this->trackCache('miss', $key, 0);
            return null;
        }

        $data = json_decode($cached, true);

        // Decompress if needed
        if ($data['compressed'] ?? false) {
            $data['content'] = $this->decompress($data['content']);
        }

        $this->trackCache('hit', $key, strlen($data['content']));

        return $data;
    }

    /**
     * Invalidate cache by key
     */
    public function invalidate(string $key): void
    {
        $cacheKey = $this->getCacheKey($key);
        Redis::del($cacheKey);
        $this->trackCache('invalidate', $key, 0);
    }

    /**
     * Invalidate all cached items with specific tags
     */
    public function invalidateTags(array $tags): int
    {
        $invalidated = 0;

        foreach ($tags as $tag) {
            $tagKey = $this->getTagKey($tag);
            $keys = Redis::smembers($tagKey);

            if (!empty($keys)) {
                // Remove all cached pages with this tag
                $invalidated += Redis::del(...$keys);
                // Clear the tag set
                Redis::del($tagKey);
            }

            Log::info("Cache invalidated for tag: {$tag}, keys: {$invalidated}");
        }

        return $invalidated;
    }

    /**
     * Invalidate all page caches
     */
    public function flushAll(): void
    {
        $pattern = $this->prefix . '*';
        $keys = Redis::keys($pattern);

        if (!empty($keys)) {
            Redis::del(...$keys);
            Log::info('Flushed all page caches', ['count' => count($keys)]);
        }

        // Also flush all tag sets
        $tagPattern = $this->prefix . 'tag:*';
        $tagKeys = Redis::keys($tagPattern);

        if (!empty($tagKeys)) {
            Redis::del(...$tagKeys);
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $pattern = $this->prefix . 'stats:*';
        $keys = Redis::keys($pattern);

        $stats = [
            'total_keys' => count(Redis::keys($this->prefix . '*')),
            'hits' => 0,
            'misses' => 0,
            'size' => 0,
            'hits_rate' => 0,
        ];

        foreach ($keys as $key) {
            $type = str_replace($this->prefix . 'stats:', '', $key);

            if ($type === 'hits') {
                $stats['hits'] = (int) Redis::get($key);
            } elseif ($type === 'misses') {
                $stats['misses'] = (int) Redis::get($key);
            } elseif ($type === 'size') {
                $stats['size'] = (int) Redis::get($key);
            }
        }

        $total = $stats['hits'] + $stats['misses'];
        $stats['hits_rate'] = $total > 0 ? round(($stats['hits'] / $total) * 100, 2) : 0;

        return $stats;
    }

    /**
     * Warm cache with popular pages
     */
    public function warmCache(array $urls): void
    {
        Log::info('Starting cache warming', ['urls' => count($urls)]);

        foreach ($urls as $url) {
            try {
                // Make HTTP request to warm cache
                $response = \Http::get($url);

                if ($response->successful()) {
                    Log::info("Cache warmed for: {$url}");
                }
            } catch (\Exception $e) {
                Log::error("Failed to warm cache for: {$url}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Generate cache key from URL or identifier
     */
    private function getCacheKey(string $key): string
    {
        return $this->prefix . md5($key);
    }

    /**
     * Generate tag key
     */
    private function getTagKey(string $tag): string
    {
        return $this->prefix . 'tag:' . $tag;
    }

    /**
     * Add cache key to tag set
     */
    private function addKeyToTag(string $tag, string $cacheKey, int $ttl): void
    {
        $tagKey = $this->getTagKey($tag);
        Redis::sadd($tagKey, $cacheKey);
        Redis::expire($tagKey, $ttl);
    }

    /**
     * Get cacheable headers (filter out dynamic headers)
     */
    private function getCacheableHeaders(array $headers): array
    {
        $cacheable = [
            'content-type',
            'content-encoding',
            'cache-control',
            'etag',
            'last-modified',
        ];

        return array_filter($headers, function ($key) use ($cacheable) {
            return in_array(strtolower($key), $cacheable);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Compress content if it exceeds threshold
     */
    private function maybeCompress(string $content): string
    {
        if ($this->shouldCompress($content)) {
            return gzcompress($content, 6);
        }

        return $content;
    }

    /**
     * Determine if content should be compressed
     */
    private function shouldCompress(string $content): bool
    {
        return $this->compressionEnabled &&
               strlen($content) > $this->compressionThreshold;
    }

    /**
     * Decompress content
     */
    private function decompress(string $content): string
    {
        return gzuncompress($content);
    }

    /**
     * Track cache statistics
     */
    private function trackCache(string $action, string $key, int $size): void
    {
        $statsKey = $this->prefix . 'stats:' . $action;

        if ($action === 'hit' || $action === 'miss' || $action === 'set') {
            Redis::incr($statsKey);
        }

        if ($action === 'set') {
            $sizeKey = $this->prefix . 'stats:size';
            Redis::incrby($sizeKey, $size);
        }

        // Expire stats after 24 hours
        Redis::expire($statsKey, 86400);
    }

    /**
     * Clear cache statistics
     */
    public function clearStats(): void
    {
        $pattern = $this->prefix . 'stats:*';
        $keys = Redis::keys($pattern);

        if (!empty($keys)) {
            Redis::del(...$keys);
        }

        Log::info('Cache statistics cleared');
    }

    /**
     * Get all cached keys
     */
    public function getAllKeys(): array
    {
        $pattern = $this->prefix . '*';
        $keys = Redis::keys($pattern);

        // Filter out stats and tag keys
        return array_filter($keys, function ($key) {
            return !str_contains($key, 'stats:') && !str_contains($key, 'tag:');
        });
    }

    /**
     * Get cache info for specific key
     */
    public function getCacheInfo(string $key): ?array
    {
        $cacheKey = $this->getCacheKey($key);
        $ttl = Redis::ttl($cacheKey);

        if ($ttl === -2) {
            return null; // Key doesn't exist
        }

        $data = Redis::get($cacheKey);
        if (!$data) {
            return null;
        }

        $decoded = json_decode($data, true);

        return [
            'key' => $key,
            'cache_key' => $cacheKey,
            'ttl' => $ttl,
            'size' => strlen($data),
            'tags' => $decoded['tags'] ?? [],
            'cached_at' => $decoded['cached_at'] ?? null,
            'compressed' => $decoded['compressed'] ?? false,
        ];
    }

    /**
     * Extend TTL for cached item
     */
    public function extendTtl(string $key, int $ttl): bool
    {
        $cacheKey = $this->getCacheKey($key);
        return Redis::expire($cacheKey, $ttl);
    }
}
