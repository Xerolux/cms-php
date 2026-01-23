<?php

namespace Tests\Feature\Post;

use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $apiVersion = 'api/v1';
    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_index_returns_paginated_posts()
    {
        Post::factory()->count(25)->create(['author_id' => $this->user->id]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/posts");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_index_filters_by_status()
    {
        Post::factory()->create(['status' => 'published', 'author_id' => $this->user->id]);
        Post::factory()->create(['status' => 'draft', 'author_id' => $this->user->id]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/posts?status=published");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertEquals('published', $response->json('data.0.status'));
    }

    public function test_index_can_search_posts()
    {
        Post::factory()->create([
            'title' => 'Laravel Tutorial',
            'author_id' => $this->user->id
        ]);
        Post::factory()->create([
            'title' => 'Python Guide',
            'author_id' => $this->user->id
        ]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/posts?search=Laravel");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_store_creates_new_post()
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/posts", [
                'title' => 'Test Post',
                'content' => 'This is test content',
                'status' => 'draft',
                'category_ids' => [$category->id],
                'tag_ids' => [$tag->id],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'content',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'title' => 'Test Post',
            'slug' => 'test-post',
        ]);
    }

    public function test_store_generates_unique_slug()
    {
        Post::factory()->create(['title' => 'Test Post', 'slug' => 'test-post']);

        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/posts", [
                'title' => 'Test Post',
                'content' => 'Content',
            ]);

        $response->assertStatus(201);

        $post = Post::where('title', 'Test Post')->latest()->first();
        $this->assertNotEquals('test-post', $post->slug);
    }

    public function test_store_requires_title()
    {
        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/posts", [
                'content' => 'Content',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_requires_content()
    {
        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/posts", [
                'title' => 'Test Post',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_show_returns_post()
    {
        $post = Post::factory()->create(['author_id' => $this->user->id]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $post->id,
                    'title' => $post->title,
                ],
            ]);
    }

    public function test_show_includes_relationships()
    {
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $featuredImage = Media::factory()->create();

        $post = Post::factory()->create(['author_id' => $this->user->id]);
        $post->categories()->attach($category->id);
        $post->tags()->attach($tag->id);
        $post->update(['featured_image_id' => $featuredImage->id]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/posts/{$post->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'tags',
                    'featured_image',
                ],
            ]);
    }

    public function test_update_modifies_post()
    {
        $post = Post::factory()->create(['author_id' => $this->user->id]);

        $response = $this->withToken($this->token)
            ->putJson("{$this->apiVersion}/posts/{$post->id}", [
                'title' => 'Updated Title',
                'content' => $post->content,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'title' => 'Updated Title',
                ],
            ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_update_can_change_status()
    {
        $post = Post::factory()->create([
            'author_id' => $this->user->id,
            'status' => 'draft'
        ]);

        $response = $this->withToken($this->token)
            ->putJson("{$this->apiVersion}/posts/{$post->id}", [
            'title' => $post->title,
            'content' => $post->content,
            'status' => 'published',
            'published_at' => now()->toIso8601String(),
        ]);

        $response->assertStatus(200);

        $this->assertEquals('published', $post->fresh()->status);
        $this->assertNotNull($post->fresh()->published_at);
    }

    public function test_delete_removes_post()
    {
        $post = Post::factory()->create(['author_id' => $this->user->id]);

        $response = $this->withToken($this->token)
            ->deleteJson("{$this->apiVersion}/posts/{$post->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('posts', [
            'id' => $post->id,
        ]);
    }

    public function test_bulk_store_creates_multiple_posts()
    {
        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/posts/bulk", [
                'posts' => [
                    ['title' => 'Post 1', 'content' => 'Content 1'],
                    ['title' => 'Post 2', 'content' => 'Content 2'],
                    ['title' => 'Post 3', 'content' => 'Content 3'],
                ],
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseCount('posts', 3);
    }

    public function test_bulk_delete_removes_multiple_posts()
    {
        $posts = Post::factory()->count(3)->create(['author_id' => $this->user->id]);

        $response = $this->withToken($this->token)
            ->deleteJson("{$this->apiVersion}/posts/bulk", [
                'ids' => $posts->pluck('id')->toArray(),
            ]);

        $response->assertStatus(204);

        foreach ($posts as $post) {
            $this->assertSoftDeleted('posts', [
                'id' => $post->id,
            ]);
        }
    }

    public function test_auto_save_creates_or_updates_draft()
    {
        $post = Post::factory()->create([
            'author_id' => $this->user->id,
            'status' => 'draft'
        ]);

        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/posts/{$post->id}/auto-save", [
            'title' => 'Auto Saved Title',
            'content' => 'Auto saved content',
        ]);

        $response->assertStatus(200);

        $this->assertEquals('Auto Saved Title', $post->fresh()->title);
    }

    public function test_unauthenticated_user_cannot_access_posts()
    {
        $response = $this->getJson("{$this->apiVersion}/posts");

        $response->assertStatus(401);
    }

    public function test_user_can_only_see_own_posts_by_default()
    {
        $otherUser = User::factory()->create();
        Post::factory()->create(['author_id' => $otherUser->id]);
        Post::factory()->create(['author_id' => $this->user->id]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/posts");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_by_category()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $post1 = Post::factory()->create(['author_id' => $this->user->id]);
        $post2 = Post::factory()->create(['author_id' => $this->user->id]);

        $post1->categories()->attach($category1->id);
        $post2->categories()->attach($category2->id);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/posts?category_id={$category1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_filter_by_tag()
    {
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $post1 = Post::factory()->create(['author_id' => $this->user->id]);
        $post2 = Post::factory()->create(['author_id' => $this->user->id]);

        $post1->tags()->attach($tag1->id);
        $post2->tags()->attach($tag2->id);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/posts?tag_id={$tag1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_posts_can_be_sorted_by_date()
    {
        Post::factory()->create([
            'author_id' => $this->user->id,
            'created_at' => now()->subDays(2)
        ]);
        Post::factory()->create([
            'author_id' => $this->user->id,
            'created_at' => now()->subDay()
        ]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/posts?sort=created_at&order=desc");

        $response->assertStatus(200);

        $dates = collect($response->json('data'))->pluck('created_at');
        $this->assertEquals($dates->sort()->values(), $dates);
    }
}
