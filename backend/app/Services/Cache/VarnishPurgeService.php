<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Varnish Cache Purge Service
 *
 * Handles cache invalidation for Varnish HTTP accelerator
 */
class VarnishPurgeService
{
    private string $varnishHost;
    private int $varnishPort;
    private array $allowedIps;

    public function __construct()
    {
        $this->varnishHost = config('cache.varnish.host', 'varnish');
        $this->varnishPort = config('cache.varnish.port', 6081);
        $this->allowedIps = config('cache.varnish.allowed_ips', ['127.0.0.1']);
    }

    /**
     * Purge specific URL from Varnish cache
     */
    public function purgeUrl(string $url): bool
    {
        try {
            $response = Http::withHeaders([
                'Host' => request()->getHost(),
                'X-Purge-Method' => 'default',
            ])->send('PURGE', $this->getVarnishUrl($url));

            $success = $response->successful();

            if ($success) {
                Log::info("Varnish cache purged for URL: {$url}");
            } else {
                Log::warning("Failed to purge Varnish cache for URL: {$url}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Exception while purging Varnish cache for URL: {$url}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Purge multiple URLs from Varnish cache
     */
    public function purgeUrls(array $urls): int
    {
        $purged = 0;

        foreach ($urls as $url) {
            if ($this->purgeUrl($url)) {
                $purged++;
            }
        }

        Log::info("Varnish cache purged for {$purged}/" . count($urls) . " URLs");

        return $purged;
    }

    /**
     * Ban all URLs matching pattern
     */
    public function banPattern(string $pattern): bool
    {
        try {
            $response = Http::withHeaders([
                'Host' => request()->getHost(),
                'X-Ban-Url' => $pattern,
            ])->send('BAN', $this->getVarnishUrl('/'));

            $success = $response->successful();

            if ($success) {
                Log::info("Varnish cache banned pattern: {$pattern}");
            } else {
                Log::warning("Failed to ban Varnish cache pattern: {$pattern}");
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Exception while banning Varnish cache pattern: {$pattern}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Ban specific cache tags
     */
    public function banTags(array $tags): bool
    {
        try {
            $tagPattern = '(' . implode('|', $tags) . ')';

            $response = Http::withHeaders([
                'Host' => request()->getHost(),
                'X-Cache-Tags' => $tagPattern,
            ])->send('BAN', $this->getVarnishUrl('/'));

            $success = $response->successful();

            if ($success) {
                Log::info("Varnish cache banned tags: " . implode(', ', $tags));
            } else {
                Log::warning("Failed to ban Varnish cache tags: " . implode(', ', $tags));
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Exception while banning Varnish cache tags", [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Purge entire cache
     */
    public function purgeAll(): bool
    {
        return $this->banPattern('.*');
    }

    /**
     * Purge cache for specific post
     */
    public function purgePost(int $postId): void
    {
        $patterns = [
            "/api/v1/posts/{$postId}",
            "/api/v1/posts",
            "/api/v1/categories",
            "/api/v1/tags",
            "/posts/{$postId}",
        ];

        foreach ($patterns as $pattern) {
            $this->purgeUrl($pattern);
        }

        // Also ban by tags
        $this->banTags(["post:{$postId}", 'posts', 'homepage']);
    }

    /**
     * Purge cache for specific category
     */
    public function purgeCategory(int $categoryId): void
    {
        $patterns = [
            "/api/v1/categories/{$categoryId}",
            "/api/v1/categories",
            "/api/v1/posts",
            "/categories/{$categoryId}",
        ];

        foreach ($patterns as $pattern) {
            $this->purgeUrl($pattern);
        }

        $this->banTags(["category:{$categoryId}", 'categories', 'posts']);
    }

    /**
     * Purge cache for specific tag
     */
    public function purgeTag(int $tagId): void
    {
        $patterns = [
            "/api/v1/tags/{$tagId}",
            "/api/v1/tags",
            "/api/v1/posts",
            "/tags/{$tagId}",
        ];

        foreach ($patterns as $pattern) {
            $this->purgeUrl($pattern);
        }

        $this->banTags(["tag:{$tagId}", 'tags', 'posts']);
    }

    /**
     * Get full Varnish URL
     */
    private function getVarnishUrl(string $path): string
    {
        return "http://{$this->varnishHost}:{$this->varnishPort}{$path}";
    }

    /**
     * Warm cache by fetching URLs
     */
    public function warmCache(array $urls): void
    {
        Log::info('Starting Varnish cache warming', ['urls' => count($urls)]);

        foreach ($urls as $url) {
            try {
                Http::get($url);
                Log::debug("Varnish cache warmed for: {$url}");
            } catch (\Exception $e) {
                Log::error("Failed to warm Varnish cache for: {$url}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
