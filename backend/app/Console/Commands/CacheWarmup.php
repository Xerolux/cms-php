<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Cache\CacheWarmupService;
use Illuminate\Support\Facades\Log;

class CacheWarmup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm
                            {--type=all : Type of warmup (all|popular|recent|critical|sitemap)}
                            {--tags= : Comma-separated list of tags to warm}
                            {--urls= : Comma-separated list of URLs to warm}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm cache by preloading content';

    private CacheWarmupService $warmupService;

    public function __construct(CacheWarmupService $warmupService)
    {
        parent::__construct();
        $this->warmupService = $warmupService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $type = $this->option('type');
        $tags = $this->option('tags');
        $urls = $this->option('urls');

        $this->info("Starting cache warmup (type: {$type})");

        try {
            switch ($type) {
                case 'all':
                    $this->warmAll();
                    break;

                case 'popular':
                    $this->warmPopular();
                    break;

                case 'recent':
                    $this->warmRecent();
                    break;

                case 'critical':
                    $this->warmCritical();
                    break;

                case 'sitemap':
                    $this->warmSitemap();
                    break;

                case 'tags':
                    if (!$tags) {
                        $this->error('Tags option is required for type=tags');
                        return 1;
                    }
                    $this->warmTags($tags);
                    break;

                case 'urls':
                    if (!$urls) {
                        $this->error('URLs option is required for type=urls');
                        return 1;
                    }
                    $this->warmUrls($urls);
                    break;

                default:
                    $this->error("Unknown type: {$type}");
                    return 1;
            }

            $this->info('Cache warmup completed successfully');

            $stats = $this->warmupService->getStats();
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Warmed URLs', $stats['warmed_count']],
                    ['Failed URLs', $stats['failed_count']],
                    ['Last Warmup', $stats['last_warmup']],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $this->error("Cache warmup failed: {$e->getMessage()}");
            Log::error('Cache warmup command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }

    private function warmAll(): void
    {
        $this->info('Warming all caches...');
        $results = $this->warmupService->warmAll();

        $this->info("Warmed: {$results['warmed']} URLs");
        if ($results['failed'] > 0) {
            $this->warn("Failed: {$results['failed']} URLs");
        }
    }

    private function warmPopular(): void
    {
        $this->info('Warming popular pages...');
        $this->warmupService->warmPopularPages();
        $this->info('Popular pages warmed');
    }

    private function warmRecent(): void
    {
        $this->info('Warming recently updated content...');
        $this->warmupService->warmRecentlyUpdated();
        $this->info('Recent content warmed');
    }

    private function warmCritical(): void
    {
        $this->info('Warming critical pages...');
        $this->warmupService->preloadCriticalPages();
        $this->info('Critical pages warmed');
    }

    private function warmSitemap(): void
    {
        $this->info('Warming sitemap...');
        $this->warmupService->warmSitemap();
        $this->info('Sitemap warmed');
    }

    private function warmTags(string $tags): void
    {
        $tagArray = explode(',', $tags);
        $this->info('Warming tags: ' . implode(', ', $tagArray));
        $this->warmupService->warmTags($tagArray);
        $this->info('Tags warmed');
    }

    private function warmUrls(string $urls): void
    {
        $urlArray = explode(',', $urls);
        $this->info('Warming URLs: ' . count($urlArray) . ' URLs');
        $results = $this->warmupService->warmUrls($urlArray);

        $this->info("Warmed: {$results['success']} URLs");
        if ($results['failed'] > 0) {
            $this->warn("Failed: {$results['failed']} URLs");
        }
    }
}
