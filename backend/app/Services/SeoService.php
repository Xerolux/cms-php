<?php

namespace App\Services;

use App\Models\Post;

class SeoService
{
    /**
     * Generiert Open Graph Meta Tags für einen Post
     */
    public function generateOpenGraphTags(Post $post): array
    {
        $baseUrl = config('app.url');
        $imageUrl = $post->featuredImage?->url ?? null;
        $siteName = config('app.name');

        return [
            'og:type' => 'article',
            'og:site_name' => $siteName,
            'og:title' => $post->meta_title ?: $post->title,
            'og:description' => $post->meta_description ?: $post->excerpt,
            'og:url' => $baseUrl . '/blog/' . $post->slug,
            'og:image' => $imageUrl ?: $baseUrl . '/default-og-image.jpg',
            'og:image:width' => $post->featuredImage?->width ?? 1200,
            'og:image:height' => $post->featuredImage?->height ?? 630,
            'og:image:alt' => $post->featuredImage?->alt_text,
            'article:published_time' => $post->published_at?->toAtomString(),
            'article:modified_time' => $post->updated_at->toAtomString(),
            'article:author' => $post->author?->display_name ?: $post->author?->name,
            'article:section' => $post->categories->first()?->name,
            'article:tag' => $post->tags->pluck('name')->toArray(),
        ];
    }

    /**
     * Generiert Twitter Card Meta Tags
     */
    public function generateTwitterCardTags(Post $post): array
    {
        $baseUrl = config('app.url');
        $imageUrl = $post->featuredImage?->url ?? null;
        $siteName = config('app.name');

        return [
            'twitter:card' => 'summary_large_image',
            'twitter:site' => '@yourusername', // TODO: Konfigurierbar machen
            'twitter:creator' => '@yourusername',
            'twitter:title' => $post->meta_title ?: $post->title,
            'twitter:description' => $post->meta_description ?: $post->excerpt,
            'twitter:image' => $imageUrl ?: $baseUrl . '/default-twitter-image.jpg',
            'twitter:image:alt' => $post->featuredImage?->alt_text,
        ];
    }

    /**
     * Generiert Schema.org JSON-LD Structured Data
     */
    public function generateStructuredData(Post $post): string
    {
        $baseUrl = config('app.url');
        $author = $post->author;

        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $post->meta_description ?: $post->excerpt,
            'image' => $post->featuredImage?->url,
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at->toAtomString(),
            'author' => [
                '@type' => 'Person',
                'name' => $author->display_name ?: $author->name,
                'url' => $baseUrl . '/author/' . $author->id,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $baseUrl . '/logo.png',
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $baseUrl . '/blog/' . $post->slug,
            ],
        ];

        // Breadcrumbs hinzufügen
        if ($post->categories->isNotEmpty()) {
            $structuredData['breadcrumb'] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => $baseUrl,
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => 'Blog',
                        'item' => $baseUrl . '/blog',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => $post->categories->first()->name,
                        'item' => $baseUrl . '/category/' . $post->categories->first()->slug,
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 4,
                        'name' => $post->title,
                        'item' => $baseUrl . '/blog/' . $post->slug,
                    ],
                ],
            ];
        }

        return json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Generiert Canonical URL
     */
    public function generateCanonicalUrl(Post $post): string
    {
        $baseUrl = config('app.url');
        return $baseUrl . '/blog/' . $post->slug;
    }

    /**
     * Generiert Meta Robots Tags
     */
    public function generateMetaRobots(Post $post): string
    {
        if ($post->status === 'published') {
            return 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
        }

        return 'noindex, nofollow';
    }

    /**
     * Generiert kompletten SEO Context für einen Post
     */
    public function getSeoContext(Post $post): array
    {
        return [
            'title' => $this->generateMetaTitle($post),
            'description' => $this->generateMetaDescription($post),
            'canonical' => $this->generateCanonicalUrl($post),
            'robots' => $this->generateMetaRobots($post),
            'og' => $this->generateOpenGraphTags($post),
            'twitter' => $this->generateTwitterCardTags($post),
            'structured_data' => $this->generateStructuredData($post),
        ];
    }

    /**
     * Generiert optimierten Meta Title
     */
    protected function generateMetaTitle(Post $post): string
    {
        $siteName = config('app.name');

        if ($post->meta_title) {
            return $post->meta_title . ' | ' . $siteName;
        }

        // Titel auf 60 Zeichen begrenzen
        $title = $post->title;
        if (strlen($title) > 50) {
            $title = substr($title, 0, 47) . '...';
        }

        return $title . ' | ' . $siteName;
    }

    /**
     * Generiert optimierte Meta Description
     */
    protected function generateMetaDescription(Post $post): string
    {
        if ($post->meta_description) {
            return $post->meta_description;
        }

        // Excerpt verwenden, auf 160 Zeichen begrenzen
        $description = $post->excerpt ?: strip_tags($post->content);
        if (strlen($description) > 157) {
            $description = substr($description, 0, 154) . '...';
        }

        return $description;
    }
}
