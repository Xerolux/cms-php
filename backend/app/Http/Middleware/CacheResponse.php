<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    protected int $cacheTime = 3600; // 1 Stunde Standard

    public function handle(Request $request, Closure $next, ?int $minutes = null): Response
    {
        $cacheTime = $minutes ?? $this->cacheTime;

        // Nur Cache für GET Requests
        if ($request->isMethod('get')) {
            $cacheKey = $this->getCacheKey($request);

            // Prüfen ob Cache existiert
            if (Cache::has($cacheKey)) {
                $cachedResponse = Cache::get($cacheKey);

                // ETag Header für Browser-Caching
                $etag = md5($cachedResponse['content']);

                return response($cachedResponse['content'])
                    ->header('Content-Type', $cachedResponse['headers']['Content-Type'] ?? 'application/json')
                    ->header('X-Cache', 'HIT')
                    ->header('ETag', $etag);
            }

            $response = $next($request);

            // Cache die Response
            if ($response->getStatusCode() === 200) {
                Cache::put($cacheKey, [
                    'content' => $response->getContent(),
                    'headers' => $response->headers->all(),
                ], $cacheTime * 60);

                $response->header('X-Cache', 'MISS');
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
    }

    /**
     * Löscht gesamten Cache
     */
    public static function clearAll(): void
    {
        Cache::flush();
    }
}
