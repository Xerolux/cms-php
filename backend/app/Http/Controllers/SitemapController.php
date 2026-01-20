<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class SitemapController extends Controller
{
    /**
     * Generiert XML Sitemap
     */
    public function index(): Response
    {
        $posts = Post::where('status', 'published')
            ->orderBy('updated_at', 'desc')
            ->get();

        $categories = Category::all();
        $tags = Tag::all();

        $sitemap = $this->generateSitemap($posts, $categories, $tags);

        return response($sitemap)
            ->header('Content-Type', 'text/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Generiert Sitemap XML
     */
    protected function generateSitemap($posts, $categories, $tags): string
    {
        $baseUrl = config('app.url');
        $date = now()->toAtomString();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        $xml .= $this->generateUrlNode($baseUrl, '1.0', 'daily');
        $xml .= $this->generateUrlNode($baseUrl . '/blog', '0.9', 'daily');

        // Posts
        foreach ($posts as $post) {
            $priority = $this->calculatePriority($post);
            $changeFreq = 'weekly';
            $xml .= $this->generateUrlNode(
                $baseUrl . '/blog/' . $post->slug,
                $priority,
                $changeFreq,
                $post->updated_at?->toAtomString()
            );
        }

        // Categories
        foreach ($categories as $category) {
            $xml .= $this->generateUrlNode(
                $baseUrl . '/category/' . $category->slug,
                '0.8',
                'weekly',
                $category->updated_at?->toAtomString()
            );
        }

        // Tags
        foreach ($tags as $tag) {
            $xml .= $this->generateUrlNode(
                $baseUrl . '/tag/' . $tag->slug,
                '0.6',
                'weekly',
                $tag->updated_at?->toAtomString()
            );
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Generiert einen URL-Node für Sitemap
     */
    protected function generateUrlNode(
        string $url,
        string $priority = '0.5',
        string $changeFreq = 'monthly',
        ?string $lastMod = null
    ): string {
        $node = '  <url>' . "\n";
        $node .= '    <loc>' . htmlspecialchars($url) . '</loc>' . "\n";

        if ($lastMod) {
            $node .= '    <lastmod>' . $lastMod . '</lastmod>' . "\n";
        }

        $node .= '    <changefreq>' . $changeFreq . '</changefreq>' . "\n";
        $node .= '    <priority>' . $priority . '</priority>' . "\n";
        $node .= '  </url>' . "\n";

        return $node;
    }

    /**
     * Berechnet Priority basierend auf Views und Alter
     */
    protected function calculatePriority($post): string
    {
        $priority = 0.5;

        // Höhere Priority für mehr Views
        if ($post->view_count > 1000) {
            $priority += 0.3;
        } elseif ($post->view_count > 500) {
            $priority += 0.2;
        } elseif ($post->view_count > 100) {
            $priority += 0.1;
        }

        // Höhere Priority für aktuelle Posts
        $daysOld = $post->created_at->diffInDays(now());
        if ($daysOld < 7) {
            $priority += 0.2;
        } elseif ($daysOld < 30) {
            $priority += 0.1;
        }

        return min(number_format($priority, 1), '1.0');
    }

    /**
     * Sitemap speichern
     */
    public function store(): \Illuminate\Http\JsonResponse
    {
        $posts = Post::where('status', 'published')
            ->orderBy('updated_at', 'desc')
            ->get();

        $categories = Category::all();
        $tags = Tag::all();

        $sitemap = $this->generateSitemap($posts, $categories, $tags);

        // Speichern in public/storage
        Storage::disk('public')->put('sitemap.xml', $sitemap);

        return response()->json([
            'message' => 'Sitemap generated successfully',
            'url' => Storage::disk('public')->url('sitemap.xml'),
        ]);
    }
}
