<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SearchService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    protected SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Einfache Suche über alle Inhalte
     */
    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'category_id' => 'nullable|exists:categories,id',
            'tag_id' => 'nullable|exists:tags,id',
            'language' => 'nullable|in:de,en',
        ]);

        $results = $this->searchService->search(
            $validated['q'],
            array_filter([
                'category_id' => $validated['category_id'] ?? null,
                'tag_id' => $validated['tag_id'] ?? null,
                'language' => $validated['language'] ?? null,
            ])
        );

        // Suche loggen für Analytics
        $resultsCount = count($results['posts']);
        $this->searchService->logSearch($validated['q'], $resultsCount, auth()->id());

        return response()->json([
            'query' => $validated['q'],
            'results' => $results,
            'total' => $resultsCount,
        ]);
    }

    /**
     * Suchvorschläge für Autocomplete
     */
    public function suggestions(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:50',
        ]);

        $suggestions = $this->searchService->suggestions($validated['q']);

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Verwandte Posts für einen bestimmten Post
     */
    public function relatedPosts($postId)
    {
        $post = \App\Models\Post::findOrFail($postId);
        $related = $this->searchService->relatedPosts($post);

        return response()->json([
            'post_id' => $postId,
            'related_posts' => $related,
        ]);
    }

    /**
     * Erweiterte Suche mit Facetten
     */
    public function advancedSearch(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|min:2|max:100',
            'categories' => 'array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'array',
            'tags.*' => 'exists:tags,id',
            'language' => 'nullable|in:de,en',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'author' => 'nullable|exists:users,id',
            'per_page' => 'nullable|integer|max:100',
        ]);

        $results = $this->searchService->advancedSearch($validated);

        return response()->json($results);
    }

    /**
     * Trending Suchanfragen
     */
    public function trending(Request $request)
    {
        $limit = min($request->input('limit', 10), 50);
        $trending = $this->searchService->trendingSearches($limit);

        return response()->json([
            'trending' => $trending,
        ]);
    }

    /**
     * Such-Statistiken für Admin Dashboard
     */
    public function stats(Request $request)
    {
        $period = $request->input('period', '7 days');

        $totalSearches = \DB::table('search_queries')
            ->where('searched_at', '>=', now()->sub($period))
            ->count();

        $uniqueQueries = \DB::table('search_queries')
            ->where('searched_at', '>=', now()->sub($period))
            ->distinct('query_text')
            ->count('query_text');

        $avgResults = \DB::table('search_queries')
            ->where('searched_at', '>=', now()->sub($period))
            ->avg('results_count');

        $topQueries = $this->searchService->trendingSearches(10);

        $noResultsSearches = \DB::table('search_queries')
            ->where('searched_at', '>=', now()->sub($period))
            ->where('results_count', 0)
            ->select('query_text', DB::raw('COUNT(*) as count'))
            ->groupBy('query_text')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'total_searches' => $totalSearches,
            'unique_queries' => $uniqueQueries,
            'avg_results' => round($avgResults, 2),
            'top_queries' => $topQueries,
            'no_results_searches' => $noResultsSearches,
        ]);
    }
}
