<?php

namespace App\Services\Edge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Edge Service
 *
 * Handles Cloudflare CDN and Workers integration
 */
class CloudflareService
{
    private string $apiToken;
    private string $accountId;
    private string $zoneId;
    private string $apiUrl = 'https://api.cloudflare.com/client/v4';

    public function __construct()
    {
        $this->apiToken = config('services.cloudflare.api_token');
        $this->accountId = config('services.cloudflare.account_id');
        $this->zoneId = config('services.cloudflare.zone_id');
    }

    /**
     * Purge cache by URL
     */
    public function purgeUrl(string $url): bool
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/zones/{$this->zoneId}/purge_cache", [
                    'files' => [$url],
                ]);

            $success = $response->successful();

            if ($success) {
                Log::info("Cloudflare cache purged for URL: {$url}");
            } else {
                Log::warning("Failed to purge Cloudflare cache for URL: {$url}", [
                    'response' => $response->json(),
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Exception while purging Cloudflare cache for URL: {$url}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Purge cache by multiple URLs
     */
    public function purgeUrls(array $urls): bool
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/zones/{$this->zoneId}/purge_cache", [
                    'files' => $urls,
                ]);

            $success = $response->successful();

            if ($success) {
                Log::info("Cloudflare cache purged for URLs", ['count' => count($urls)]);
            } else {
                Log::warning("Failed to purge Cloudflare cache for URLs", [
                    'response' => $response->json(),
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Exception while purging Cloudflare cache for URLs", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Purge cache by tags
     */
    public function purgeTags(array $tags): bool
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/zones/{$this->zoneId}/purge_cache", [
                    'tags' => $tags,
                ]);

            $success = $response->successful();

            if ($success) {
                Log::info("Cloudflare cache purged for tags", ['tags' => $tags]);
            } else {
                Log::warning("Failed to purge Cloudflare cache for tags", [
                    'tags' => $tags,
                    'response' => $response->json(),
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Exception while purging Cloudflare cache for tags", [
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
        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/zones/{$this->zoneId}/purge_cache", [
                    'purge_everything' => true,
                ]);

            $success = $response->successful();

            if ($success) {
                Log::info('Cloudflare entire cache purged');
            } else {
                Log::warning('Failed to purge entire Cloudflare cache', [
                    'response' => $response->json(),
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Exception while purging entire Cloudflare cache', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Add cache tag to response
     */
    public function addCacheHeaders($response, array $tags): void
    {
        $response->headers->set('Cache-Tag', implode(',', $tags));

        // Add cache control headers
        $response->headers->set('CDN-Cache-Control', 'public, max-age=3600, s-maxage=3600');
    }

    /**
     * Get cache analytics
     */
    public function getCacheAnalytics(string $since = '-24h'): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->apiUrl}/zones/{$this->zoneId}/analytics/dashboard", [
                    'since' => $since,
                ]);

            if ($response->successful()) {
                return $response->json()['result'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Exception while fetching Cloudflare analytics', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Set page rule for caching
     */
    public function setCacheRule(string $pattern, array $settings): bool
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/zones/{$this->zoneId}/pagerules", [
                    'pattern' => $pattern,
                    'actions' => $this->formatPageRuleActions($settings),
                    'status' => 'active',
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception while setting Cloudflare page rule', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Format page rule actions
     */
    private function formatPageRuleActions(array $settings): array
    {
        $actions = [];

        if (isset($settings['cache_level'])) {
            $actions[] = [
                'id' => 'cache_level',
                'value' => $settings['cache_level'],
            ];
        }

        if (isset($settings['edge_cache_ttl'])) {
            $actions[] = [
                'id' => 'edge_cache_ttl',
                'value' => $settings['edge_cache_ttl'],
            ];
        }

        if (isset($settings['browser_cache_ttl'])) {
            $actions[] = [
                'id' => 'browser_cache_ttl',
                'value' => $settings['browser_cache_ttl'],
            ];
        }

        if (isset($settings['cache_key'])) {
            $actions[] = [
                'id' => 'cache_key',
                'value' => $settings['cache_key'],
            ];
        }

        return $actions;
    }

    /**
     * Get zone information
     */
    public function getZoneInfo(): array
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->apiUrl}/zones/{$this->zoneId}");

            if ($response->successful()) {
                return $response->json()['result'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Exception while fetching Cloudflare zone info', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Set security headers
     */
    public function setSecurityHeaders(array $headers): bool
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->patch("{$this->apiUrl}/zones/{$this->zoneId}/settings/security_header", [
                    'strict_transport_security' => $headers['hsts'] ?? [
                        'enabled' => true,
                        'max_age' => 31536000,
                        'include_subdomains' => true,
                        'nosniff' => true,
                    ],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception while setting Cloudflare security headers', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Configure SSL/TLS
     */
    public function configureSSL(string $mode = 'strict'): bool
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->patch("{$this->apiUrl}/zones/{$this->zoneId}/settings/ssl", [
                    'value' => $mode,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception while configuring Cloudflare SSL', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Enable HTTP/3
     */
    public function enableHTTP3(bool $enabled = true): bool
    {
        try {
            $response = Http::withToken($this->apiToken)
                ->patch("{$this->apiUrl}/zones/{$this->zoneId}/settings/http3", [
                    'value' => $enabled ? 'on' : 'off',
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Exception while enabling Cloudflare HTTP/3', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get edge IP ranges
     */
    public function getEdgeIPRanges(): array
    {
        try {
            $response = Http::get('https://www.cloudflare.com/ips-v4');

            if ($response->successful()) {
                return explode("\n", trim($response->body()));
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Exception while fetching Cloudflare IP ranges', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Webhook for cache invalidation
     */
    public function handleWebhook(array $payload): void
    {
        $type = $payload['type'] ?? '';

        switch ($type) {
            case 'post_updated':
                $this->purgeTags([
                    "post:{$payload['post_id']}",
                    'posts',
                    'homepage',
                ]);
                break;

            case 'category_updated':
                $this->purgeTags([
                    "category:{$payload['category_id']}",
                    'categories',
                    'posts',
                ]);
                break;

            case 'cache_clear':
                $this->purgeAll();
                break;

            default:
                Log::warning('Unknown webhook type', ['type' => $type]);
        }
    }
}
