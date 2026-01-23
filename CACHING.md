# Advanced Caching System for XQUANTORIA

This document describes the comprehensive caching infrastructure implemented for XQUANTORIA.

## Table of Contents

1. [Redis Full-Page Caching](#redis-full-page-caching)
2. [Varnish HTTP Accelerator](#varnish-http-accelerator)
3. [HTTP/2 Server Push](#http2-server-push)
4. [Database Query Optimization](#database-query-optimization)
5. [Redis Clustering](#redis-clustering)
6. [Cache Warming](#cache-warming)
7. [Edge Computing](#edge-computing)

---

## Redis Full-Page Caching

### Features

- **Full HTTP Response Caching**: Complete page responses stored in Redis
- **Cache Tags**: Organize cached content by resource (posts, categories, tags)
- **Automatic Invalidation**: Smart cache invalidation on content updates
- **Compression**: Gzip compression for responses > 10KB
- **Statistics**: Track cache hits, misses, and hit rate

### Usage

```php
use App\Services\Cache\RedisPageCacheService;

class PostController extends Controller
{
    public function show($slug)
    {
        $post = Post::where('slug', $slug)->firstOrFail();

        // Automatic caching via middleware
        return view('posts.show', compact('post'));
    }

    public function update(Request $request, Post $post)
    {
        $post->update($request->validated());

        // Invalidate related caches
        CacheResponse::clearPostCache($post->id);

        return redirect()->back();
    }
}
```

### Service Methods

```php
$cache = app(RedisPageCacheService::class);

// Get cache statistics
$stats = $cache->getStats();

// Warm specific URLs
$cache->warmCache([
    'https://example.com/posts/my-post',
    'https://example.com/categories/news',
]);

// Get cache info for specific key
$info = $cache->getCacheInfo('posts:show:my-post');

// Extend TTL
$cache->extendTtl('posts:show:my-post', 7200); // 2 hours
```

---

## Varnish HTTP Accelerator

### Features

- **Backend Configuration**: Separate backends for API and frontend
- **Cache Rules**: Different TTL for API, HTML, and static assets
- **Purge Endpoints**: PURGE, BAN support for cache invalidation
- **Grace Mode**: Serve stale content during backend issues
- **HTTP/2 Support**: Full HTTP/2 compatibility

### Configuration Files

- `docker/varnish/default.vcl`: Main Varnish configuration
- Port 6081: HTTP cache
- Port 6082: Admin interface

### Cache Rules

| Content Type | TTL | Grace Mode |
|--------------|-----|------------|
| Static Assets (CSS, JS, Images) | 24 hours | - |
| API Responses | 1 minute | 5 minutes |
| HTML Pages | 1 hour | 24 hours |

### Purge Commands

```php
use App\Services\Cache\VarnishPurgeService;

$varnish = app(VarnishPurgeService::class);

// Purge specific URL
$varnish->purgeUrl('https://example.com/posts/my-post');

// Purge multiple URLs
$varnish->purgeUrls([
    'https://example.com/posts',
    'https://example.com/categories',
]);

// Ban by pattern
$varnish->banPattern('/api/v1/posts/*');

// Ban by cache tags
$varnish->banTags(['post:123', 'posts', 'homepage']);

// Purge entire cache
$varnish->purgeAll();
```

### Docker Profiles

```bash
# Development: No Varnish
docker-compose up

# Production: With Varnish
docker-compose --profile production up
docker-compose --profile full up
```

---

## HTTP/2 Server Push

### Features

- **Asset Preloading**: Critical CSS, JS, and fonts preloaded
- **Critical CSS Inlining**: Above-the-fold CSS inlined
- **Font Optimization**: font-display: swap for all fonts
- **Preconnect**: External origins preconnected
- **DNS Prefetch**: Third-party domains prefetched

### Middleware

```php
// In app/Http/Kernel.php
protected $middleware = [
    // ...
    \App\Http\Middleware\Http2ServerPush::class,
];
```

### Link Headers

The middleware automatically adds Link headers for:

- Critical CSS files
- JavaScript bundles
- Web fonts (with crossorigin)
- Preconnect to CDN/external origins
- DNS prefetch for analytics

### Configuration

```php
// config/cache.php
'http2' => [
    'enabled' => true,
    'push_enabled' => true,
    'preload_critical_css' => true,
    'preload_fonts' => true,
    'preload_scripts' => true,
],
```

---

## Database Query Optimization

### Features

- **Index Analysis**: Automatic index recommendations
- **N+1 Detection**: Detect and prevent N+1 query problems
- **Query Caching**: Cache expensive database queries
- **Slow Query Logging**: Track slow queries automatically
- **Bulk Operations**: Optimized bulk insert/updates

### Service Usage

```php
use App\Services\Database\QueryOptimizerService;

$optimizer = app(QueryOptimizerService::class);

// Analyze table indexes
$analysis = $optimizer->analyzeIndexes('posts');

// Get recommendations
foreach ($analysis['recommendations'] as $rec) {
    if ($rec['priority'] === 'high') {
        $optimizer->createIndex('posts', 'slug');
    }
}

// Detect N+1 problems
$problems = $optimizer->detectNPlusOneQueries();

// Cache query results
$posts = $optimizer->cacheQuery('popular_posts', function () {
    return Post::orderBy('view_count', 'desc')->limit(20)->get();
}, 3600);

// Get table statistics
$stats = $optimizer->getTableStats('posts');
```

### Model Trait

```php
use App\Models\Traits\PreventsNPlusOne;

class Post extends Model
{
    use PreventsNPlusOne;

    protected function getDefaultEagerLoads(): array
    {
        return ['category', 'tags', 'author'];
    }
}

// Use in queries
Post::withEagerLoad()->get();

// Detect N+1 problems
Post::detectNPlusOne(function () {
    $posts = Post::all();
    foreach ($posts as $post) {
        echo $post->category->name; // N+1 detected!
    }
}, log: true);
```

---

## Redis Clustering

### Features

- **Master-Slave Replication**: Automatic data replication
- **Read/Write Splitting**: Reads from slave, writes to master
- **Automatic Failover**: Sentinel-based failover
- **Connection Pooling**: Persistent connections
- **Health Monitoring**: Automatic health checks

### Configuration

```bash
# Single instance (Development)
docker-compose up

# Cluster (Production)
docker-compose --profile cluster up

# Full stack with all caching
docker-compose --profile full up
```

### Service Usage

```php
use App\Services\Cache\RedisClusterService;

$cluster = app(RedisClusterService::class);

// Automatic read/write splitting
$value = $cluster->get('key'); // Reads from slave
$cluster->set('key', 'value'); // Writes to master

// Get cluster information
$info = $cluster->getClusterInfo();

// Get connection stats
$stats = $cluster->getConnectionStats();

// Execute pipeline
$results = $cluster->pipeline(function ($pipe) {
    $pipe->set('key1', 'value1');
    $pipe->set('key2', 'value2');
    $pipe->get('key1');
});

// Execute transaction
$results = $cluster->transaction(function ($tx) {
    $tx->incr('counter');
    $tx->get('counter');
});
```

### Components

- **redis-master**: Port 6379, primary write database
- **redis-slave**: Port 6380, read replica
- **redis-sentinel**: Port 26379, failover manager

---

## Cache Warming

### Features

- **Scheduled Preloading**: Automatic cache warming via cron
- **Popular Pages**: Detect and warm popular content
- **Sitemap-Based**: Warm URLs from sitemap.xml
- **Tag-Based**: Warm content by cache tags
- **Batch Processing**: Efficient batch processing

### Artisan Commands

```bash
# Warm all caches
php artisan cache:warm

# Warm only popular pages
php artisan cache:warm --type=popular

# Warm recently updated content
php artisan cache:warm --type=recent

# Warm critical pages only
php artisan cache:warm --type=critical

# Warm by sitemap
php artisan cache:warm --type=sitemap

# Warm specific tags
php artisan cache:warm --type=tags --tags="post:123,posts,homepage"

# Warm specific URLs
php artisan cache:warm --type=urls --urls="https://example.com/page1,https://example.com/page2"
```

### Scheduling

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Warm popular pages every 30 minutes
    $schedule->command('cache:warm --type=popular')
        ->everyThirtyMinutes()
        ->sendOutputTo(storage_path('logs/cache-warm.log'));

    // Warm recent content every hour
    $schedule->command('cache:warm --type=recent')
        ->hourly();

    // Full warmup twice daily
    $schedule->command('cache:warm --type=all')
        ->twiceDaily(1, 13);
}
```

### Service Usage

```php
use App\Services\Cache\CacheWarmupService;

$warmup = app(CacheWarmupService::class);

// Warm all caches
$results = $warmup->warmAll();

// Warm specific content
$warmup->warmHomepage();
$warmup->warmPosts();
$warmup->warmCategories();

// Warm by tags
$warmup->warmTags(['post:123', 'category:5']);

// Preload critical pages
$warmup->preloadCriticalPages();

// Get statistics
$stats = $warmup->getStats();
```

---

## Edge Computing

### Cloudflare Workers

Features:
- Edge caching at Cloudflare's 300+ locations
- Automatic Brotli compression
- Geographic routing
- Bot detection and optimization
- Rate limiting
- Cache analytics

### Worker Configuration

Location: `edge/cloudflare/worker.js`

### Deployment

```bash
cd edge/cloudflare
npm install
wrangler deploy
```

### Service Usage

```php
use App\Services\Edge\CloudflareService;

$cloudflare = app(CloudflareService::class);

// Purge cache by URL
$cloudflare->purgeUrl('https://example.com/posts/my-post');

// Purge multiple URLs
$cloudflare->purgeUrls([
    'https://example.com/posts',
    'https://example.com/categories',
]);

// Purge by tags
$cloudflare->purgeTags(['post:123', 'posts']);

// Purge entire cache
$cloudflare->purgeAll();

// Set cache rules
$cloudflare->setCacheRule('/api/v1/*', [
    'cache_level' => 'cache_everything',
    'edge_cache_ttl' => 3600,
    'browser_cache_ttl' => 3600,
]);

// Get analytics
$analytics = $cloudflare->getCacheAnalytics('-24h');

// Enable HTTP/3
$cloudflare->enableHTTP3(true);
```

### Configuration

Add to `.env`:

```env
CLOUDFLARE_ENABLED=true
CLOUDFLARE_API_TOKEN=your_api_token
CLOUDFLARE_ACCOUNT_ID=your_account_id
CLOUDFLARE_ZONE_ID=your_zone_id
```

---

## Performance Monitoring

### Cache Statistics

```bash
# Redis statistics
redis-cli INFO stats

# Varnish statistics
varnishstat

# Laravel cache statistics
php artisan cache:stats
```

### Slow Query Logging

```bash
# Enable slow query logging
php artisan tinker
>>> DB::enableQueryLog();

# View slow queries
>>> app(QueryOptimizerService::class)->getSlowQueries();
```

---

## Best Practices

### 1. Cache Invalidation

Always invalidate caches after updates:

```php
public function update(Request $request, Post $post)
{
    $post->update($request->validated());

    // Invalidate at all levels
    CacheResponse::clearPostCache($post->id);
    app(VarnishPurgeService::class)->purgePost($post->id);
    app(CloudflareService::class)->purgeTags(["post:{$post->id}"]);

    return redirect()->back();
}
```

### 2. Eager Loading

Prevent N+1 problems:

```php
// Bad - N+1 problem
$posts = Post::all();
foreach ($posts as $post) {
    echo $post->category->name; // Query for each post!
}

// Good - Eager loading
$posts = Post::with('category')->get();
foreach ($posts as $post) {
    echo $post->category->name; // No additional queries!
}
```

### 3. Database Indexing

Add indexes for frequently queried columns:

```php
$optimizer->createIndex('posts', 'slug');
$optimizer->createIndex('posts', 'status');
$optimizer->createIndex('posts', 'published_at');
```

### 4. Cache Headers

Set appropriate cache headers:

```php
return response($content)
    ->header('Cache-Control', 'public, max-age=3600, s-maxage=3600')
    ->header('X-Cache-Tags', 'post:123,posts');
```

### 5. Monitor Performance

Regularly check cache performance:

```bash
php artisan cache:warm --type=all
php artisan cache:stats
```

---

## Troubleshooting

### Cache Not Working

1. Check Redis connection:
```bash
docker-compose exec redis redis-cli ping
```

2. Check Varnish status:
```bash
docker-compose exec varnish varnishadm ping
```

3. Check cache configuration:
```bash
php artisan config:cache
php artisan cache:clear
```

### High Memory Usage

1. Check Redis memory:
```bash
docker-compose exec redis redis-cli INFO memory
```

2. Adjust maxmemory in redis.conf

3. Enable compression in page cache

### Slow Queries

1. Enable slow query logging
2. Run index analysis:
```bash
php artisan tinker
>>> app(QueryOptimizerService::class)->analyzeIndexes('posts');
```

3. Add recommended indexes

---

## Additional Resources

- [Redis Documentation](https://redis.io/documentation)
- [Varnish Documentation](https://varnish-cache.org/docs/)
- [HTTP/2 Specification](https://httpwg.org/specs/rfc7540.html)
- [Cloudflare Workers](https://developers.cloudflare.com/workers/)
- [Laravel Caching](https://laravel.com/docs/cache)

---

## Support

For issues or questions, please refer to the main XQUANTORIA documentation or create an issue in the GitHub repository.
