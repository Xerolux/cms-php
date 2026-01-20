<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SearchService
{
    /**
     * Führt eine Volltextsuche durch
     */
    public function search(string $query, array $filters = []): array
    {
        $results = [
            'posts' => $this->searchPosts($query, $filters),
            'categories' => $this->searchCategories($query),
            'tags' => $this->searchTags($query),
        ];

        return $results;
    }

    /**
     * Durchsucht Posts mit PostgreSQL Full Text Search
     */
    protected function searchPosts(string $query, array $filters = []): array
    {
        $posts = Post::query()
            ->with(['author', 'categories', 'tags', 'featuredImage'])
            ->where('status', 'published')
            ->where(function ($q) use ($query) {
                // PostgreSQL Full Text Search mit tsvector
                $q->whereRaw("to_tsvector('german', coalesce(title, '') || ' ' || coalesce(content, '')) @@ to_tsquery('german', ?)", [$query])
                  ->orWhere('title', 'ILIKE', "%{$query}%")
                  ->orWhere('content', 'ILIKE', "%{$query}%")
                  ->orWhere('excerpt', 'ILIKE', "%{$query}%");
            });

        // Filter nach Kategorie
        if (!empty($filters['category_id'])) {
            $posts->whereHas('categories', function ($q) use ($filters) {
                $q->where('categories.id', $filters['category_id']);
            });
        }

        // Filter nach Tag
        if (!empty($filters['tag_id'])) {
            $posts->whereHas('tags', function ($q) use ($filters) {
                $q->where('tags.id', $filters['tag_id']);
            });
        }

        // Filter nach Sprache
        if (!empty($filters['language'])) {
            $posts->where('language', $filters['language']);
        }

        // Ranking nach Relevanz
        $posts->selectRaw("
            posts.*,
            ts_rank(to_tsvector('german', coalesce(title, '') || ' ' || coalesce(content, '')), to_tsquery('german', ?)) as rank,
            (view_count * 0.1) as popularity_score
        ", [$query]);

        // Sortierung: Kombination aus Relevanz und Popularität
        $posts->orderByRaw('(rank + popularity_score) DESC')
              ->orderBy('created_at', 'desc');

        $results = $posts->limit(20)->get();

        // Highlight Suchbegriffe im Excerpt
        $results->each(function ($post) use ($query) {
            $post->highlighted_excerpt = $this->highlightSearchTerms($post->excerpt ?: $post->content, $query);
        });

        return $results->toArray();
    }

    /**
     * Durchsucht Kategorien
     */
    protected function searchCategories(string $query): array
    {
        return Category::where('name', 'ILIKE', "%{$query}%")
            ->orWhere('description', 'ILIKE', "%{$query}%")
            ->withCount('posts')
            ->orderBy('posts_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Durchsucht Tags
     */
    protected function searchTags(string $query): array
    {
        return Tag::where('name', 'ILIKE', "%{$query}%")
            ->orderBy('usage_count', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    /**
     * Suchvorschläge für Autocomplete
     */
    public function suggestions(string $query, int $limit = 10): array
    {
        // Titel Vorschläge
        $titleSuggestions = Post::where('status', 'published')
            ->where('title', 'ILIKE', "%{$query}%")
            ->pluck('title')
            ->toArray();

        // Kategorie Vorschläge
        $categorySuggestions = Category::where('name', 'ILIKE', "%{$query}%")
            ->pluck('name')
            ->toArray();

        // Tag Vorschläge
        $tagSuggestions = Tag::where('name', 'ILIKE', "%{$query}%")
            ->pluck('name')
            ->toArray();

        // Zusammenführen und limitieren
        $suggestions = array_unique(array_merge(
            $titleSuggestions,
            $categorySuggestions,
            $tagSuggestions
        ));

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Verwandte Suche basierend auf einem Post
     */
    public function relatedPosts(Post $post, int $limit = 5): array
    {
        // Posts mit gleichen Kategorien oder Tags
        return Post::where('id', '!=', $post->id)
            ->where('status', 'published')
            ->where(function ($q) use ($post) {
                $q->whereHas('categories', function ($categoryQuery) use ($post) {
                    $categoryQuery->whereIn('categories.id', $post->categories->pluck('id'));
                })
                ->orWhereHas('tags', function ($tagQuery) use ($post) {
                    $tagQuery->whereIn('tags.id', $post->tags->pluck('id'));
                });
            })
            ->with(['author', 'categories', 'tags', 'featuredImage'])
            ->orderBy('view_count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Highlight Suchbegriffe im Text
     */
    protected function highlightSearchTerms(string $text, string $query): string
    {
        $words = explode(' ', $query);
        $highlighted = $text;

        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $highlighted = preg_replace(
                    '/(' . preg_quote($word, '/') . ')/i',
                    '<mark>$1</mark>',
                    $highlighted
                );
            }
        }

        // Text auf 300 Zeichen kürzen und ... hinzufügen
        if (strlen($highlighted) > 300) {
            $highlighted = substr($highlighted, 0, 300) . '...';
        }

        // HTML Tags strippen für sichere Anzeige
        return strip_tags($highlighted, '<mark>');
    }

    /**
     * Statistiken über Suchanfragen (für Analytics)
     */
    public function logSearch(string $query, int $resultsCount, ?string $userId = null): void
    {
        DB::table('search_queries')->insert([
            'query_text' => $query,
            'results_count' => $resultsCount,
            'user_id' => $userId,
            'searched_at' => now(),
        ]);
    }

    /**
     * Beliebteste Suchanfragen
     */
    public function trendingSearches(int $limit = 10): array
    {
        return DB::table('search_queries')
            ->select('query_text', DB::raw('COUNT(*) as count'))
            ->where('searched_at', '>=', now()->subDays(30))
            ->groupBy('query_text')
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Erweiterte Suche mit Facetten
     */
    public function advancedSearch(array $params): array
    {
        $query = $params['q'] ?? '';
        $categoryIds = $params['categories'] ?? [];
        $tagIds = $params['tags'] ?? [];
        $language = $params['language'] ?? null;
        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;
        $authorId = $params['author'] ?? null;

        $posts = Post::query()
            ->with(['author', 'categories', 'tags'])
            ->where('status', 'published');

        // Volltextsuche
        if ($query) {
            $posts->where(function ($q) use ($query) {
                $q->whereRaw("to_tsvector('german', coalesce(title, '') || ' ' || coalesce(content, '')) @@ to_tsquery('german', ?)", [$query])
                  ->orWhere('title', 'ILIKE', "%{$query}%")
                  ->orWhere('content', 'ILIKE', "%{$query}%");
            });
        }

        // Kategorie Filter
        if (!empty($categoryIds)) {
            $posts->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        // Tag Filter
        if (!empty($tagIds)) {
            $posts->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        // Sprache Filter
        if ($language) {
            $posts->where('language', $language);
        }

        // Datum Filter
        if ($dateFrom) {
            $posts->where('published_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $posts->where('published_at', '<=', $dateTo);
        }

        // Author Filter
        if ($authorId) {
            $posts->where('author_id', $authorId);
        }

        $results = $posts->orderBy('published_at', 'desc')
            ->paginate($params['per_page'] ?? 20);

        return $results->toArray();
    }
}
