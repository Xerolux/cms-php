<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\Cache\RedisPageCacheService;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    protected int $cacheTime = 3600; // 1 Stunde Standard
    protected RedisPageCacheService $pageCache;

    public function __construct(RedisPageCacheService $pageCache)
    {
        $this->pageCache = $pageCache;
    }

    public function handle(Request $request, Closure $next, ?int $minutes = null): Response
    {
        $cacheTime = $minutes ?? $this->cacheTime;

        // Nur Cache für GET Requests
        if ($request->isMethod('get')) {
            $cacheKey = $this->getCacheKey($request);
            $tags = $this->getCacheTags($request);

            // Try Redis Page Cache first
            $cachedResponse = $this->pageCache->getCachedResponse($cacheKey);

            if ($cachedResponse !== null) {
                // Check ETag for conditional requests
                $etag = md5($cachedResponse['content']);
                $ifNoneMatch = $request->header('If-None-Match');

                if ($ifNoneMatch === $etag) {
                    return response()->noContent()
                        ->header('X-Cache', 'HIT')
                        ->header('ETag', $etag)
                        ->setStatusCode(304);
                }

                return response($cachedResponse['content'])
                    ->setStatusCode($cachedResponse['status'])
                    ->withHeaders($cachedResponse['headers'])
                    ->header('X-Cache', 'HIT')
                    ->header('X-Cache-Tags', implode(',', $tags))
                    ->header('ETag', $etag);
            }

            $response = $next($request);

            // Cache die Response
            if ($response->getStatusCode() === 200) {
                $this->pageCache->cacheResponse(
                    $cacheKey,
                    $response,
                    $tags,
                    $cacheTime
                );

                // Legacy cache support
                Cache::put($cacheKey, [
                    'content' => $response->getContent(),
                    'headers' => $response->headers->all(),
                ], $cacheTime * 60);

                $response->header('X-Cache', 'MISS');
                $response->header('X-Cache-Tags', implode(',', $tags));
            }

            return $response;
        }

        return $next($request);
    }

    /**
     * Generiert Cache Key basierend auf Request
     */
    protected function getCacheKey(Request $request): string
    {
        $key = 'response:' . md5($request->fullUrl());

        // User-spezifischer Cache für authentifizierte Requests
        if (auth()->check()) {
            $key .= ':user:' . auth()->id();
        }

        return $key;
    }

    /**
     * Extract cache tags from request
     */
    protected function getCacheTags(Request $request): array
    {
        $tags = ['page'];

        // Add resource-specific tags
        if ($request->route('id')) {
            $tags[] = $request->route()->getName() . ':' . $request->route('id');
        }

        // Add locale tag
        if ($request->hasHeader('Accept-Language')) {
            $tags[] = 'locale:' . locale_accept_from_http($request->header('Accept-Language'));
        }

        return $tags;
    }

    /**
     * Löscht Cache für einen bestimmten Post
     */
    public static function clearPostCache(int $postId): void
    {
        $patterns = [
            "response:*post*{$postId}*",
            "response:*posts*",
            "response:*categories*",
            "response:*tags*",
        ];

        foreach ($patterns as $pattern) {
            $keys = Cache::getRedis()->keys($pattern);
            if (!empty($keys)) {
                Cache::getRedis()->del($keys);
            }
        }

        // Also clear using page cache tags
        $pageCache = app(RedisPageCacheService::class);
        $pageCache->invalidateTags([
            "post:{$postId}",
            'posts',
            'categories',
            'tags',
        ]);
    }

    /**
     * Löscht gesamten Cache
     */
    public static function clearAll(): void
    {
        Cache::flush();
        $pageCache = app(RedisPageCacheService::class);
        $pageCache->flushAll();
    }
}
