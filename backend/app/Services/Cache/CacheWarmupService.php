<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Page;
use Carbon\Carbon;

/**
 * Cache Warming Service
 *
 * Features:
 * - Scheduled cache preloading
 * - Popular pages detection
 * - Sitemap-based warming
 * - Tag-based warming
 */
class CacheWarmupService
{
    private RedisPageCacheService $pageCache;
    private VarnishPurgeService $varnishPurge;
    private array $warmedUrls = [];
    private array $failedUrls = [];

    public function __construct(
        RedisPageCacheService $pageCache,
        VarnishPurgeService $varnishPurge
    ) {
        $this->pageCache = $pageCache;
        $this->varnishPurge = $varnishPurge;
    }

    /**
     * Warm all caches
     */
    public function warmAll(): array
    {
        Log::info('Starting full cache warmup');

        $this->warmedUrls = [];
        $this->failedUrls = [];

        // Warm homepage
        $this->warmHomepage();

        // Warm popular pages
        $this->warmPopularPages();

        // Warm blog posts
        $this->warmPosts();

        // Warm categories
        $this->warmCategories();

        // Warm tags
        $this->warmTags();

        // Warm static pages
        $this->warmPages();

        // Warm API endpoints
        $this->warmApiEndpoints();

        $results = [
            'warmed' => count($this->warmedUrls),
            'failed' => count($this->failedUrls),
            'urls' => $this->warmedUrls,
            'errors' => $this->failedUrls,
        ];

        Log::info('Cache warmup completed', $results);

        return $results;
    }

    /**
     * Warm homepage
     */
    public function warmHomepage(): void
    {
        $urls = [
            route('home'),
            route('feed'),
        ];

        foreach ($urls as $url) {
            $this->warmUrl($url, 'homepage');
        }
    }

    /**
     * Warm popular pages
     */
    public function warmPopularPages(): void
    {
        // Get most viewed posts in the last 30 days
        $popularPosts = Post::published()
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->orderBy('view_count', 'desc')
            ->limit(20)
            ->get();

        foreach ($popularPosts as $post) {
            $url = route('posts.show', $post->slug);
            $this->warmUrl($url, 'popular', ['post:' . $post->id]);
        }

        Log::info('Warmed popular pages', ['count' => $popularPosts->count()]);
    }

    /**
     * Warm blog posts
     */
    public function warmPosts(): void
    {
        $posts = Post::published()->get();

        foreach ($posts as $post) {
            $url = route('posts.show', $post->slug);
            $this->warmUrl($url, 'posts', ['post:' . $post->id]);
        }

        // Warm posts index
        $this->warmUrl(route('posts.index'), 'posts');

        // Warm posts RSS feed
        $this->warmUrl(route('feed'), 'feed');

        Log::info('Warmed posts', ['count' => $posts->count()]);
    }

    /**
     * Warm categories
     */
    public function warmCategories(): void
    {
        $categories = Category::has('posts')->get();

        foreach ($categories as $category) {
            $url = route('categories.show', $category->slug);
            $this->warmUrl($url, 'categories', ['category:' . $category->id]);
        }

        // Warm categories index
        $this->warmUrl(route('categories.index'), 'categories');

        Log::info('Warmed categories', ['count' => $categories->count()]);
    }

    /**
     * Warm tags
     */
    public function warmTags(): void
    {
        $tags = Tag::has('posts')->get();

        foreach ($tags as $tag) {
            $url = route('tags.show', $tag->slug);
            $this->warmUrl($url, 'tags', ['tag:' . $tag->id]);
        }

        // Warm tags index
        $this->warmUrl(route('tags.index'), 'tags');

        Log::info('Warmed tags', ['count' => $tags->count()]);
    }

    /**
     * Warm static pages
     */
    public function warmPages(): void
    {
        $pages = Page::published()->get();

        foreach ($pages as $page) {
            $url = route('pages.show', $page->slug);
            $this->warmUrl($url, 'pages', ['page:' . $page->id]);
        }

        Log::info('Warmed pages', ['count' => $pages->count()]);
    }

    /**
     * Warm API endpoints
     */
    public function warmApiEndpoints(): void
    {
        $endpoints = [
            '/api/v1/posts',
            '/api/v1/categories',
            '/api/v1/tags',
            '/api/v1/settings',
        ];

        foreach ($endpoints as $endpoint) {
            $url = config('app.url') . $endpoint;
            $this->warmUrl($url, 'api');
        }

        Log::info('Warmed API endpoints');
    }

    /**
     * Warm specific URL
     */
    public function warmUrl(string $url, string $type = 'manual', array $tags = []): bool
    {
        try {
            $startTime = microtime(true);

            $response = Http::timeout(30)->get($url);

            $duration = microtime(true) - $startTime;

            if ($response->successful()) {
                $this->warmedUrls[] = [
                    'url' => $url,
                    'type' => $type,
                    'duration' => round($duration * 1000, 2), // milliseconds
                    'tags' => $tags,
                ];

                Log::debug("Warmed cache for: {$url}", [
                    'type' => $type,
                    'duration' => $duration,
                ]);

                return true;
            } else {
                $this->failedUrls[] = [
                    'url' => $url,
                    'type' => $type,
                    'status' => $response->status(),
                    'error' => $response->body(),
                ];

                Log::warning("Failed to warm cache for: {$url}", [
                    'status' => $response->status(),
                ]);

                return false;
            }
        } catch (\Exception $e) {
            $this->failedUrls[] = [
                'url' => $url,
                'type' => $type,
                'error' => $e->getMessage(),
            ];

            Log::error("Exception while warming cache for: {$url}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Warm multiple URLs
     */
    public function warmUrls(array $urls, string $type = 'batch'): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($urls as $url) {
            if ($this->warmUrl($url, $type)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Warm based on sitemap
     */
    public function warmSitemap(): void
    {
        try {
            $sitemapUrl = config('app.url') . '/sitemap.xml';

            $response = Http::get($sitemapUrl);

            if (!$response->successful()) {
                Log::error('Failed to fetch sitemap', [
                    'status' => $response->status(),
                ]);
                return;
            }

            $xml = simplexml_load_string($response->body());
            $urls = [];

            foreach ($xml->url as $urlElement) {
                $urls[] = (string) $urlElement->loc;
            }

            Log::info('Warming URLs from sitemap', ['count' => count($urls)]);

            // Warm URLs in batches
            collect($urls)->chunk(50)->each(function ($batch) {
                foreach ($batch as $url) {
                    $this->warmUrl($url, 'sitemap');
                }

                // Add delay between batches to avoid overwhelming the server
                usleep(500000); // 0.5 seconds
            });
        } catch (\Exception $e) {
            Log::error('Failed to warm sitemap', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Warm recently updated content
     */
    public function warmRecentlyUpdated(): void
    {
        $since = Carbon::now()->subHours(24);

        // Get recently updated posts
        $posts = Post::published()
            ->where('updated_at', '>=', $since)
            ->get();

        foreach ($posts as $post) {
            $url = route('posts.show', $post->slug);
            $this->warmUrl($url, 'recent', ['post:' . $post->id]);
        }

        // Get recently updated categories
        $categories = Category::where('updated_at', '>=', $since)->get();

        foreach ($categories as $category) {
            $url = route('categories.show', $category->slug);
            $this->warmUrl($url, 'recent', ['category:' . $category->id]);
        }

        Log::info('Warmed recently updated content', [
            'posts' => $posts->count(),
            'categories' => $categories->count(),
        ]);
    }

    /**
     * Get warming statistics
     */
    public function getStats(): array
    {
        return [
            'warmed_count' => count($this->warmedUrls),
            'failed_count' => count($this->failedUrls),
            'last_warmup' => now()->toIso8601String(),
        ];
    }

    /**
     * Schedule periodic warmup
     */
    public function schedulePeriodicWarmup(int $intervalMinutes = 60): void
    {
        Log::info('Scheduling periodic cache warmup', [
            'interval' => $intervalMinutes . ' minutes',
        ]);

        // This would typically be handled by Laravel's scheduler
        // Add to app/Console/Kernel.php:
        // $schedule->call(fn() => app(CacheWarmupService::class)->warmRecentlyUpdated())
        //     ->everyThirtyMinutes();
    }

    /**
     * Warm specific tags
     */
    public function warmTags(array $tags): void
    {
        foreach ($tags as $tag) {
            if (str_starts_with($tag, 'post:')) {
                $postId = str_replace('post:', '', $tag);
                $post = Post::find($postId);

                if ($post) {
                    $this->warmUrl(route('posts.show', $post->slug), 'tag', [$tag]);
                }
            } elseif (str_starts_with($tag, 'category:')) {
                $categoryId = str_replace('category:', '', $tag);
                $category = Category::find($categoryId);

                if ($category) {
                    $this->warmUrl(route('categories.show', $category->slug), 'tag', [$tag]);
                }
            } elseif (str_starts_with($tag, 'tag:')) {
                $tagId = str_replace('tag:', '', $tag);
                $tagModel = Tag::find($tagId);

                if ($tagModel) {
                    $this->warmUrl(route('tags.show', $tagModel->slug), 'tag', [$tag]);
                }
            }
        }
    }

    /**
     * Preload critical pages for faster initial load
     */
    public function preloadCriticalPages(): void
    {
        $criticalUrls = [
            route('home'),
            route('posts.index'),
            config('app.url') . '/api/v1/posts',
            config('app.url') . '/api/v1/categories',
        ];

        Log::info('Preloading critical pages');

        foreach ($criticalUrls as $url) {
            $this->warmUrl($url, 'critical');
        }
    }
}
