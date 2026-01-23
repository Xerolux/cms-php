<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
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

    public function test_index_returns_all_categories()
    {
        Category::factory()->count(15)->create();

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/categories");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                    ],
                ],
            ]);
    }

    public function test_index_includes_post_count()
    {
        $category = Category::factory()->create();
        Post::factory()->count(5)->create()->each(function ($post) use ($category) {
            $post->categories()->attach($category->id);
        });

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/categories");

        $response->assertStatus(200);

        $categoryData = collect($response->json('data'))->firstWhere('id', $category->id);
        $this->assertEquals(5, $categoryData['posts_count']);
    }

    public function test_store_creates_category()
    {
        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/categories", [
                'name' => 'Technology',
                'description' => 'Tech related posts',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Technology',
                    'slug' => 'technology',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Technology',
            'slug' => 'technology',
        ]);
    }

    public function test_store_generates_unique_slug()
    {
        Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);

        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/categories", [
                'name' => 'Technology',
            ]);

        $response->assertStatus(201);

        $category = Category::where('name', 'Technology')->latest()->first();
        $this->assertNotEquals('technology', $category->slug);
    }

    public function test_store_requires_name()
    {
        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/categories", [
                'description' => 'Test description',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_unique_name()
    {
        Category::factory()->create(['name' => 'Technology']);

        $response = $this->withToken($this->token)
            ->postJson("{$this->apiVersion}/categories", [
                'name' => 'Technology',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_show_returns_category()
    {
        $category = Category::factory()->create();

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
            ]);
    }

    public function test_show_includes_posts()
    {
        $category = Category::factory()->create();
        Post::factory()->count(3)->create()->each(function ($post) use ($category) {
            $post->categories()->attach($category->id);
        });

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'posts',
                ],
            ]);

        $this->assertCount(3, $response->json('data.posts'));
    }

    public function test_update_modifies_category()
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->withToken($this->token)
            ->putJson("{$this->apiVersion}/categories/{$category->id}", [
                'name' => 'New Name',
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'New Name',
                    'description' => 'Updated description',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
        ]);
    }

    public function test_update_generates_new_slug_when_name_changes()
    {
        $category = Category::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

        $this->withToken($this->token)
            ->putJson("{$this->apiVersion}/categories/{$category->id}", [
                'name' => 'New Name',
            ]);

        $this->assertNotEquals('old-name', $category->fresh()->slug);
    }

    public function test_delete_removes_category()
    {
        $category = Category::factory()->create();

        $response = $this->withToken($this->token)
            ->deleteJson("{$this->apiVersion}/categories/{$category->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_delete_detaches_posts()
    {
        $category = Category::factory()->create();
        $post = Post::factory()->create();
        $post->categories()->attach($category->id);

        $this->withToken($this->token)
            ->deleteJson("{$this->apiVersion}/categories/{$category->id}");

        $this->assertDatabaseMissing('category_post', [
            'category_id' => $category->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_categories()
    {
        $response = $this->getJson("{$this->apiVersion}/categories");

        $response->assertStatus(401);
    }

    public function test_categories_can_be_searched()
    {
        Category::factory()->create(['name' => 'Technology']);
        Category::factory()->create(['name' => 'Programming']);
        Category::factory()->create(['name' => 'Design']);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/categories?search=Tech");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_categories_can_be_sorted()
    {
        Category::factory()->create(['name' => 'B Category']);
        Category::factory()->create(['name' => 'A Category']);
        Category::factory()->create(['name' => 'C Category']);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/categories?sort=name&order=asc");

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertEquals(['A Category', 'B Category', 'C Category'], $names->toArray());
    }

    public function test_nested_categories_are_supported()
    {
        $parent = Category::factory()->create(['name' => 'Parent']);
        $child = Category::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);

        $response = $this->withToken($this->token)
            ->getJson("{$this->apiVersion}/categories/{$parent->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'children',
                ],
            ]);

        $this->assertCount(1, $response->json('data.children'));
    }
}
