<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;

class RecommendationEngineService
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get related posts using ML-based similarity
     */
    public function getRelatedPosts(Post $post, int $limit = 5): Collection
    {
        return Cache::remember("related_posts_{$post->id}_{$limit}", 3600, function () use ($post, $limit) {
            // Get posts from same category
            $categoryPosts = Post::where('category_id', $post->category_id)
                ->where('id', '!=', $post->id)
                ->where('status', 'published')
                ->limit($limit * 2)
                ->get();

            // Score each post based on multiple factors
            $scoredPosts = $categoryPosts->map(function ($relatedPost) use ($post) {
                return [
                    'post' => $relatedPost,
                    'score' => $this->calculateSimilarityScore($post, $relatedPost),
                ];
            });

            // Sort by score and take top results
            return collect($scoredPosts)
                ->sortByDesc('score')
                ->take($limit)
                ->pluck('post');
        });
    }

    /**
     * Calculate similarity score between two posts
     */
    protected function calculateSimilarityScore(Post $post1, Post $post2): float
    {
        $score = 0;

        // Tag overlap (30%)
        $tags1 = $post1->tags->pluck('id')->toArray();
        $tags2 = $post2->tags->pluck('id')->toArray();
        $tagOverlap = count(array_intersect($tags1, $tags2)) / max(count(array_union($tags1, $tags2)), 1);
        $score += $tagOverlap * 0.3;

        // Category match (20%)
        if ($post1->category_id === $post2->category_id) {
            $score += 0.2;
        }

        // Content similarity using text comparison (30%)
        $contentSimilarity = $this->calculateTextSimilarity(
            $post1->title . ' ' . $post1->excerpt,
            $post2->title . ' ' . $post2->excerpt
        );
        $score += $contentSimilarity * 0.3;

        // Recency bonus (20%) - more recent posts get slight boost
        $daysDiff = now()->diffInDays($post2->created_at);
        $recencyBonus = max(0, 1 - ($daysDiff / 365)); // Decay over a year
        $score += $recencyBonus * 0.2;

        return $score;
    }

    /**
     * Calculate text similarity using simple word overlap
     */
    protected function calculateTextSimilarity(string $text1, string $text2): float
    {
        $words1 = array_unique(str_word_count(strtolower($text1), 1));
        $words2 = array_unique(str_word_count(strtolower($text2), 1));

        if (empty($words1) || empty($words2)) {
            return 0;
        }

        $intersection = count(array_intersect($words1, $words2));
        $union = count(array_union($words1, $words2));

        return $intersection / max($union, 1);
    }

    /**
     * Get personalized content feed for user
     */
    public function getPersonalizedFeed(User $user, int $limit = 20): Collection
    {
        return Cache::remember("personalized_feed_{$user->id}_{$limit}", 1800, function () use ($user, $limit) {
            // Get user's reading history
            $readPosts = $user->postViews()
                ->pluck('post_id')
                ->toArray();

            // Get user's preferred categories
            $preferredCategories = $this->getUserPreferredCategories($user);

            // Get user's preferred tags
            $preferredTags = $this->getUserPreferredTags($user);

            // Build query with multiple boost factors
            $query = Post::where('status', 'published')
                ->whereNotIn('id', $readPosts)
                ->with(['category', 'tags', 'author']);

            // Boost preferred categories
            if (!empty($preferredCategories)) {
                $query->whereIn('category_id', $preferredCategories)
                    ->orderByRaw("FIELD(category_id, " . implode(',', $preferredCategories) . ") DESC");
            }

            $posts = $query->latest()->limit($limit * 2)->get();

            // Score and rank posts
            $scoredPosts = $posts->map(function ($post) use ($preferredCategories, $preferredTags) {
                return [
                    'post' => $post,
                    'score' => $this->calculatePersonalizationScore($post, $preferredCategories, $preferredTags),
                ];
            });

            return collect($scoredPosts)
                ->sortByDesc('score')
                ->take($limit)
                ->pluck('post');
        });
    }

    /**
     * Get user's preferred categories based on reading history
     */
    protected function getUserPreferredCategories(User $user): array
    {
        return $user->postViews()
            ->select('category_id', DB::raw('COUNT(*) as view_count'))
            ->join('posts', 'post_views.post_id', '=', 'posts.id')
            ->groupBy('category_id')
            ->orderByDesc('view_count')
            ->limit(5)
            ->pluck('category_id')
            ->toArray();
    }

    /**
     * Get user's preferred tags based on reading history
     */
    protected function getUserPreferredTags(User $user): array
    {
        return DB::table('post_tags')
            ->select('tag_id', DB::raw('COUNT(*) as count'))
            ->whereIn('post_id', function ($query) use ($user) {
                $query->select('post_id')
                    ->from('post_views')
                    ->where('user_id', $user->id);
            })
            ->groupBy('tag_id')
            ->orderByDesc('count')
            ->limit(10)
            ->pluck('tag_id')
            ->toArray();
    }

    /**
     * Calculate personalization score for a post
     */
    protected function calculatePersonalizationScore(Post $post, array $preferredCategories, array $preferredTags): float
    {
        $score = 0;

        // Category preference (40%)
        $categoryRank = array_search($post->category_id, $preferredCategories);
        if ($categoryRank !== false) {
            $score += (1 - ($categoryRank / max(count($preferredCategories), 1))) * 0.4;
        }

        // Tag preference (40%)
        $postTags = $post->tags->pluck('id')->toArray();
        $tagMatches = array_intersect($postTags, $preferredTags);
        if (!empty($preferredTags)) {
            $score += (count($tagMatches) / count($preferredTags)) * 0.4;
        }

        // Recency (20%)
        $daysDiff = now()->diffInDays($post->created_at);
        $recencyBonus = max(0, 1 - ($daysDiff / 30)); // Decay over 30 days
        $score += $recencyBonus * 0.2;

        return $score;
    }

    /**
     * Track user behavior for recommendations
     */
    public function trackUserBehavior(User $user, Post $post, string $action): void
    {
        // Update user's interaction data
        // This can be extended to store in a separate analytics table
        Cache::forget("personalized_feed_{$user->id}_*");
    }

    /**
     * Get trending posts based on engagement
     */
    public function getTrendingPosts(int $limit = 10, int $days = 7): Collection
    {
        return Cache::remember("trending_posts_{$limit}_{$days}", 3600, function () use ($limit, $days) {
            $startDate = now()->subDays($days);

            return Post::where('status', 'published')
                ->where('created_at', '>=', $startDate)
                ->with(['category', 'tags', 'author'])
                ->withCount(['views' => function ($query) use ($startDate) {
                    $query->where('created_at', '>=', $startDate);
                }])
                ->orderByDesc('views_count')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Collaborative filtering: Users who liked this also liked
     */
    public function getCollaborativeRecommendations(Post $post, int $limit = 5): Collection
    {
        return Cache::remember("collab_recs_{$post->id}_{$limit}", 3600, function () use ($post, $limit) {
            // Get users who viewed this post
            $userIds = $post->views()
                ->where('created_at', '>=', now()->subDays(30))
                ->pluck('user_id')
                ->unique()
                ->take(100);

            if ($userIds->isEmpty()) {
                return collect();
            }

            // Get other posts these users viewed
            $otherPosts = Post::whereHas('views', function ($query) use ($userIds) {
                $query->whereIn('user_id', $userIds)
                    ->where('created_at', '>=', now()->subDays(30));
            })
                ->where('id', '!=', $post->id)
                ->where('status', 'published')
                ->withCount(['views' => function ($query) use ($userIds) {
                    $query->whereIn('user_id', $userIds);
                }])
                ->orderByDesc('views_count')
                ->limit($limit)
                ->get();

            return $otherPosts;
        });
    }

    /**
     * Get content-based recommendations for search results
     */
    public function getSearchRecommendations(string $query, int $limit = 5): Collection
    {
        // Get posts matching the search query
        $matchingPosts = Post::search($query)
            ->where('status', 'published')
            ->take($limit * 3)
            ->get();

        if ($matchingPosts->isEmpty()) {
            return collect();
        }

        // Use AI to find related posts
        $relatedPosts = collect();
        foreach ($matchingPosts as $post) {
            $aiSuggestions = $this->aiService->suggestRelatedPosts(
                $post->title,
                $post->content,
                []
            );

            $relatedPosts = $relatedPosts->merge($aiSuggestions);
        }

        // Remove duplicates and already shown posts
        return $relatedPosts
            ->unique('id')
            ->reject(fn ($post) => $matchingPosts->contains('id', $post->id))
            ->take($limit);
    }

    /**
     * Get personalized recommendations based on user's current context
     */
    public function getContextualRecommendations(User $user, ?Post $currentPost, int $limit = 5): Collection
    {
        if ($currentPost) {
            // Combine related posts with personalized recommendations
            $relatedPosts = $this->getRelatedPosts($currentPost, $limit);
            $personalizedPosts = $this->getPersonalizedFeed($user, $limit);

            // Merge and deduplicate
            return $relatedPosts->merge($personalizedPosts)
                ->unique('id')
                ->take($limit);
        }

        // No current post, return personalized feed
        return $this->getPersonalizedFeed($user, $limit);
    }

    /**
     * Update user's preference model
     */
    public function updateUserPreferences(User $user, array $preferences): void
    {
        // Store user preferences in cache or database
        // This can be used to refine the recommendation algorithm
        Cache::put("user_preferences_{$user->id}", $preferences, 86400 * 30); // 30 days
    }

    /**
     * Get user's stored preferences
     */
    public function getUserPreferences(User $user): array
    {
        return Cache::get("user_preferences_{$user->id}", [
            'preferred_categories' => [],
            'preferred_tags' => [],
            'reading_time_preference' => 'medium',
            'content_types' => ['article', 'tutorial', 'news'],
        ]);
    }
}
