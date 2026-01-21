<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_view_published_posts()
    {
        $post = Post::factory()->create(['status' => 'published']);

        $response = $this->getJson('/api/v1/posts');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => $post->title]);
    }

    public function test_guest_cannot_create_posts()
    {
        $response = $this->postJson('/api/v1/posts', [
            'title' => 'New Post',
            'content' => 'Content here',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_create_post()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/posts', [
            'title' => 'My New Post',
            'content' => 'Amazing content',
            'status' => 'draft',
            'language' => 'en'
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['title' => 'My New Post']);

        $this->assertDatabaseHas('posts', [
            'title' => 'My New Post',
            'author_id' => $user->id
        ]);
    }

    public function test_user_can_update_own_post()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = Post::factory()->create(['author_id' => $user->id]);

        $response = $this->putJson("/api/v1/posts/{$post->id}", [
            'title' => 'Updated Title',
            'content' => 'Updated content',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Updated Title']);
    }

    public function test_user_can_delete_own_post()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $post = Post::factory()->create(['author_id' => $user->id]);

        $response = $this->deleteJson("/api/v1/posts/{$post->id}");

        $response->assertStatus(204); // Or 200 depending on implementation
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }
}
