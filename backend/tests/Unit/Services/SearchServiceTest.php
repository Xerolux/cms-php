<?php

namespace Tests\Unit\Services;

use App\Models\Post;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchServiceTest extends TestCase
{
    protected SearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SearchService();
    }

    public function test_search_returns_posts_categories_and_tags()
    {
        $post = Post::factory()->create([
            'title' => 'Test Post About Laravel',
            'status' => 'published',
        ]);

        $category = Category::factory()->create(['name' => 'Laravel']);

        $tag = Tag::factory()->create(['name' => 'laravel']);

        $results = $this->service->search('laravel');

        $this->assertArrayHasKey('posts', $results);
        $this->assertArrayHasKey('categories', $results);
        $this->assertArrayHasKey('tags', $results);
        $this->assertIsArray($results['posts']);
        $this->assertIsArray($results['categories']);
        $this->assertIsArray($results['tags']);
    }

    public function test_search_posts_filters_by_category()
    {
        $category1 = Category::factory()->create(['name' => 'PHP']);
        $category2 = Category::factory()->create(['name' => 'JavaScript']);

        $post1 = Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
        ]);
        $post1->categories()->attach($category1->id);

        $post2 = Post::factory()->create([
            'title' => 'Another Test Post',
            'status' => 'published',
        ]);
        $post2->categories()->attach($category2->id);

        $results = $this->service->search('Test', ['category_id' => $category1->id]);

        $this->assertCount(1, $results['posts']);
        $this->assertEquals($post1->id, $results['posts'][0]['id']);
    }

    public function test_search_posts_filters_by_tag()
    {
        $tag1 = Tag::factory()->create(['name' => 'PHP']);
        $tag2 = Tag::factory()->create(['name' => 'Laravel']);

        $post1 = Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
        ]);
        $post1->tags()->attach($tag1->id);

        $post2 = Post::factory()->create([
            'title' => 'Another Test Post',
            'status' => 'published',
        ]);
        $post2->tags()->attach($tag2->id);

        $results = $this->service->search('Test', ['tag_id' => $tag1->id]);

        $this->assertCount(1, $results['posts']);
        $this->assertEquals($post1->id, $results['posts'][0]['id']);
    }

    public function test_search_posts_filters_by_language()
    {
        $post1 = Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
            'language' => 'en',
        ]);

        $post2 = Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
            'language' => 'de',
        ]);

        $results = $this->service->search('Test', ['language' => 'en']);

        $this->assertCount(1, $results['posts']);
        $this->assertEquals('en', $results['posts'][0]['language']);
    }

    public function test_search_categories_returns_matching_categories()
    {
        Category::factory()->create(['name' => 'PHP Development']);
        Category::factory()->create(['name' => 'JavaScript']);

        $results = $this->service->search('PHP');

        $this->assertCount(1, $results['categories']);
        $this->assertEquals('PHP Development', $results['categories'][0]['name']);
    }

    public function test_search_tags_returns_matching_tags()
    {
        Tag::factory()->create(['name' => 'laravel']);
        Tag::factory()->create(['name' => 'php']);

        $results = $this->service->search('laravel');

        $this->assertCount(1, $results['tags']);
        $this->assertEquals('laravel', $results['tags'][0]['name']);
    }

    public function test_suggestions_returns_unique_results()
    {
        Post::factory()->create(['title' => 'Laravel Tutorial', 'status' => 'published']);
        Category::factory()->create(['name' => 'Laravel']);
        Tag::factory()->create(['name' => 'Laravel']);

        $suggestions = $this->service->suggestions('Laravel');

        $this->assertIsArray($suggestions);
        $this->assertCount(3, $suggestions);
        $this->assertContains('Laravel Tutorial', $suggestions);
        $this->assertContains('Laravel', $suggestions);
    }

    public function test_suggestions_limits_results()
    {
        Post::factory()->count(15)->create([
            'title' => 'Test Post',
            'status' => 'published',
        ]);

        $suggestions = $this->service->suggestions('Test', 10);

        $this->assertLessThanOrEqual(10, count($suggestions));
    }

    public function test_related_posts_finds_posts_by_category()
    {
        $category = Category::factory()->create();

        $post1 = Post::factory()->create([
            'title' => 'Main Post',
            'status' => 'published',
            'view_count' => 100,
        ]);
        $post1->categories()->attach($category->id);

        $post2 = Post::factory()->create([
            'title' => 'Related Post',
            'status' => 'published',
            'view_count' => 200,
        ]);
        $post2->categories()->attach($category->id);

        $related = $this->service->relatedPosts($post1, 5);

        $this->assertCount(1, $related);
        $this->assertEquals($post2->id, $related[0]['id']);
    }

    public function test_related_posts_finds_posts_by_tags()
    {
        $tag = Tag::factory()->create();

        $post1 = Post::factory()->create([
            'title' => 'Main Post',
            'status' => 'published',
            'view_count' => 100,
        ]);
        $post1->tags()->attach($tag->id);

        $post2 = Post::factory()->create([
            'title' => 'Related Post',
            'status' => 'published',
            'view_count' => 200,
        ]);
        $post2->tags()->attach($tag->id);

        $related = $this->service->relatedPosts($post1, 5);

        $this->assertCount(1, $related);
        $this->assertEquals($post2->id, $related[0]['id']);
    }

    public function test_related_posts_orders_by_view_count()
    {
        $category = Category::factory()->create();

        $post1 = Post::factory()->create([
            'title' => 'Main Post',
            'status' => 'published',
        ]);
        $post1->categories()->attach($category->id);

        $post2 = Post::factory()->create([
            'title' => 'Popular Post',
            'status' => 'published',
            'view_count' => 500,
        ]);
        $post2->categories()->attach($category->id);

        $post3 = Post::factory()->create([
            'title' => 'Less Popular Post',
            'status' => 'published',
            'view_count' => 100,
        ]);
        $post3->categories()->attach($category->id);

        $related = $this->service->relatedPosts($post1, 5);

        $this->assertEquals($post2->id, $related[0]['id']);
        $this->assertEquals($post3->id, $related[1]['id']);
    }

    public function test_highlight_search_terms_marks_words()
    {
        $text = 'This is a test about Laravel and PHP';
        $query = 'Laravel PHP';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('highlightSearchTerms');
        $method->setAccessible(true);

        $highlighted = $method->invoke($this->service, $text, $query);

        $this->assertStringContainsString('<mark>Laravel</mark>', $highlighted);
        $this->assertStringContainsString('<mark>PHP</mark>', $highlighted);
    }

    public function test_highlight_search_terms_truncates_long_text()
    {
        $longText = str_repeat('This is a very long text. ', 100);
        $query = 'long text';

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('highlightSearchTerms');
        $method->setAccessible(true);

        $highlighted = $method->invoke($this->service, $longText, $query);

        $this->assertLessThanOrEqual(303, strlen($highlighted)); // 300 + '...'
        $this->assertStringEndsWith('...', $highlighted);
    }

    public function test_log_search_inserts_to_database()
    {
        $this->service->logSearch('test query', 10, 1);

        $this->assertDatabaseHas('search_queries', [
            'query_text' => 'test query',
            'results_count' => 10,
            'user_id' => 1,
        ]);
    }

    public function test_log_search_without_user()
    {
        $this->service->logSearch('test query', 5);

        $this->assertDatabaseHas('search_queries', [
            'query_text' => 'test query',
            'results_count' => 5,
            'user_id' => null,
        ]);
    }

    public function test_trending_searches_returns_most_searched()
    {
        DB::table('search_queries')->insert([
            ['query_text' => 'laravel', 'results_count' => 10, 'searched_at' => now()->subDays(5)],
            ['query_text' => 'laravel', 'results_count' => 10, 'searched_at' => now()->subDays(3)],
            ['query_text' => 'php', 'results_count' => 5, 'searched_at' => now()->subDays(2)],
            ['query_text' => 'javascript', 'results_count' => 8, 'searched_at' => now()->subDays(10)], // outside 30 days
        ]);

        $trending = $this->service->trendingSearches(10);

        $this->assertCount(2, $trending);
        $this->assertEquals('laravel', $trending[0]['query_text']);
        $this->assertEquals(2, $trending[0]['count']);
    }

    public function test_advanced_search_with_multiple_filters()
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $post = Post::factory()->create([
            'title' => 'Advanced Laravel Tutorial',
            'status' => 'published',
            'language' => 'en',
            'published_at' => now()->subDays(5),
        ]);

        $post->categories()->attach($category->id);
        $post->tags()->attach($tag->id);

        $results = $this->service->advancedSearch([
            'q' => 'Laravel',
            'categories' => [$category->id],
            'tags' => [$tag->id],
            'language' => 'en',
            'per_page' => 20,
        ]);

        $this->assertArrayHasKey('data', $results);
        $this->assertIsArray($results['data']);
    }

    public function test_advanced_search_with_date_range()
    {
        Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
            'published_at' => now()->subDays(5),
        ]);

        Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
            'published_at' => now()->subDays(20),
        ]);

        $results = $this->service->advancedSearch([
            'q' => 'Test',
            'date_from' => now()->subDays(10)->toDateString(),
            'date_to' => now()->toDateString(),
            'per_page' => 20,
        ]);

        $this->assertCount(1, $results['data']);
    }

    public function test_advanced_search_with_author_filter()
    {
        $author1 = User::factory()->create();
        $author2 = User::factory()->create();

        Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
            'author_id' => $author1->id,
        ]);

        Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
            'author_id' => $author2->id,
        ]);

        $results = $this->service->advancedSearch([
            'q' => 'Test',
            'author' => $author1->id,
            'per_page' => 20,
        ]);

        $this->assertCount(1, $results['data']);
        $this->assertEquals($author1->id, $results['data'][0]['author_id']);
    }

    public function test_search_only_returns_published_posts()
    {
        Post::factory()->create([
            'title' => 'Published Post',
            'status' => 'published',
        ]);

        Post::factory()->create([
            'title' => 'Draft Post',
            'status' => 'draft',
        ]);

        $results = $this->service->search('Post');

        $this->assertCount(1, $results['posts']);
        $this->assertEquals('Published Post', $results['posts'][0]['title']);
    }

    public function test_search_posts_includes_relationships()
    {
        $post = Post::factory()->create([
            'title' => 'Test Post',
            'status' => 'published',
        ]);

        $results = $this->service->search('Test');

        $this->assertArrayHasKey('author', $results['posts'][0]);
        $this->assertArrayHasKey('categories', $results['posts'][0]);
        $this->assertArrayHasKey('tags', $results['posts'][0]);
        $this->assertArrayHasKey('featuredImage', $results['posts'][0]);
    }

    public function test_search_posts_highlights_excerpt()
    {
        $post = Post::factory()->create([
            'title' => 'Laravel Tutorial',
            'excerpt' => 'This is a Laravel tutorial for beginners',
            'status' => 'published',
        ]);

        $results = $this->service->search('Laravel');

        $this->assertArrayHasKey('highlighted_excerpt', $results['posts'][0]);
        $this->assertStringContainsString('<mark>', $results['posts'][0]['highlighted_excerpt']);
    }
}
