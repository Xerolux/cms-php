<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP/2 Server Push Middleware
 *
 * Adds HTTP/2 push preload headers to responses
 * for optimal resource loading performance
 */
class Http2ServerPush
{
    private array $criticalAssets = [
        'css' => [],
        'js' => [],
        'fonts' => [],
    ];

    private array $preconnectUrls = [];
    private array $dnsPrefetchUrls = [];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add push headers for HTML responses
        if (!$this->shouldOptimize($request, $response)) {
            return $response;
        }

        // Determine assets to preload based on route
        $this->loadAssetsForRoute($request);

        // Add Link headers for HTTP/2 push
        $this->addLinkHeaders($response);

        return $response;
    }

    /**
     * Determine if we should add HTTP/2 optimizations
     */
    private function shouldOptimize(Request $request, Response $response): bool
    {
        // Only for GET requests
        if (!$request->isMethod('GET')) {
            return false;
        }

        // Only for successful responses
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // Only for HTML responses
        $contentType = $response->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'text/html')) {
            return false;
        }

        return true;
    }

    /**
     * Load critical assets for current route
     */
    private function loadAssetsForRoute(Request $request): void
    {
        $routeName = $request->route()?->getName() ?? 'default';

        switch ($routeName) {
            case 'home':
            case 'index':
                $this->loadHomepageAssets();
                break;

            case 'posts.show':
                $this->loadPostAssets();
                break;

            case 'categories.show':
                $this->loadCategoryAssets();
                break;

            case 'admin.*':
                $this->loadAdminAssets();
                break;

            default:
                $this->loadDefaultAssets();
                break;
        }
    }

    /**
     * Load assets for homepage
     */
    private function loadHomepageAssets(): void
    {
        $this->criticalAssets['css'] = [
            '/assets/css/critical.css',
            '/assets/css/main.css',
        ];

        $this->criticalAssets['js'] = [
            '/assets/js/critical.js',
            '/assets/js/main.js',
        ];

        $this->criticalAssets['fonts'] = [
            '/assets/fonts/inter.woff2',
            '/assets/fonts/inter-bold.woff2',
        ];

        $this->preconnectUrls = [
            'https://cdn.example.com',
        ];

        $this->dnsPrefetchUrls = [
            '//www.google-analytics.com',
            '//stats.g.doubleclick.net',
        ];
    }

    /**
     * Load assets for blog posts
     */
    private function loadPostAssets(): void
    {
        $this->criticalAssets['css'] = [
            '/assets/css/post-critical.css',
            '/assets/css/syntax.css',
        ];

        $this->criticalAssets['js'] = [
            '/assets/js/share.js',
            '/assets/js/comments.js',
        ];

        $this->criticalAssets['fonts'] = [
            '/assets/fonts/inter.woff2',
        ];
    }

    /**
     * Load assets for category pages
     */
    private function loadCategoryAssets(): void
    {
        $this->criticalAssets['css'] = [
            '/assets/css/archive.css',
        ];

        $this->criticalAssets['js'] = [
            '/assets/js/filter.js',
        ];
    }

    /**
     * Load assets for admin panel
     */
    private function loadAdminAssets(): void
    {
        $this->criticalAssets['css'] = [
            '/assets/css/admin.css',
            '/assets/css/vendor/select2.css',
        ];

        $this->criticalAssets['js'] = [
            '/assets/js/admin.js',
            '/assets/js/vendor/vue.js',
        ];

        $this->criticalAssets['fonts'] = [
            '/assets/fonts/inter.woff2',
        ];
    }

    /**
     * Load default assets
     */
    private function loadDefaultAssets(): void
    {
        $this->criticalAssets['css'] = [
            '/assets/css/main.css',
        ];

        $this->criticalAssets['js'] = [
            '/assets/js/main.js',
        ];
    }

    /**
     * Add Link headers for HTTP/2 server push
     */
    private function addLinkHeaders(Response $response): void
    {
        $links = [];

        // Preload CSS
        foreach ($this->criticalAssets['css'] as $css) {
            $links[] = "<{$css}>; rel=preload; as=style";
        }

        // Preload JavaScript
        foreach ($this->criticalAssets['js'] as $js) {
            $links[] = "<{$js}>; rel=preload; as=script";
        }

        // Preload Fonts
        foreach ($this->criticalAssets['fonts'] as $font) {
            $links[] = "<{$font}>; rel=preload; as=font; crossorigin";
        }

        // Preconnect
        foreach ($this->preconnectUrls as $url) {
            $links[] = "<{$url}>; rel=preconnect";
        }

        // DNS Prefetch
        foreach ($this->dnsPrefetchUrls as $url) {
            $links[] = "<{$url}>; rel=dns-prefetch";
        }

        if (!empty($links)) {
            $response->headers->set('Link', implode(', ', $links));
        }
    }

    /**
     * Inline critical CSS
     */
    public function inlineCriticalCss(Response $response, string $cssContent): void
    {
        $content = $response->getContent();

        if ($content && str_contains($content, '</head>')) {
            $criticalCss = sprintf(
                '<style id="critical-css">%s</style>',
                $cssContent
            );

            $content = str_replace('</head>', $criticalCss . '</head>', $content);
            $response->setContent($content);
        }
    }

    /**
     * Add font-display: swap to font-face rules
     */
    public function optimizeFontLoading(Response $response): void
    {
        $content = $response->getContent();

        if ($content && str_contains($content, '@font-face')) {
            // Add font-display: swap to font-face declarations
            $content = preg_replace(
                '/(@font-face\s*\{[^}]*)(\})/',
                '$1font-display: swap;$2',
                $content
            );

            $response->setContent($content);
        }
    }

    /**
     * Add preload hints for images
     */
    public function preloadImages(Response $response, array $imageUrls): void
    {
        $links = [];

        foreach ($imageUrls as $url) {
            $links[] = "<{$url}>; rel=preload; as=image";
        }

        $existingLink = $response->headers->get('Link', '');
        $newLink = $existingLink ? $existingLink . ', ' . implode(', ', $links) : implode(', ', $links);

        $response->headers->set('Link', $newLink);
    }
}
