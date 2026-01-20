<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExampleContentSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create admin user
        $admin = User::where('email', 'admin@example.com')->first();
        if (!$admin) {
            $admin = User::factory()->admin()->create();
        }

        // Create additional users
        $users = User::factory()->count(5)->create();

        // Create categories
        $categories = Category::factory()->count(8)->create();

        // Create tags
        $tags = Tag::factory()->count(15)->create();

        // Create published posts
        foreach (range(1, 20) as $i) {
            $post = Post::factory()->published()->create([
                'author_id' => $users->random()->id,
            ]);

            // Attach categories
            $post->categories()->attach(
                $categories->random(rand(1, 3))->pluck('id')
            );

            // Attach tags
            $post->tags()->attach(
                $tags->random(rand(2, 5))->pluck('id')
            );

            // Add comments
            if (fake()->boolean(70)) {
                Comment::factory()->count(rand(1, 5))->approved()->create([
                    'post_id' => $post->id,
                ]);
            }
        }

        // Create scheduled posts
        Post::factory()->count(3)->scheduled()->create([
            'author_id' => $admin->id,
        ]);

        // Create draft posts
        Post::factory()->count(5)->create([
            'author_id' => $users->random()->id,
            'status' => 'draft',
        ]);

        // Create hidden posts
        Post::factory()->count(2)->hidden()->published()->create([
            'author_id' => $admin->id,
        ]);
    }
}
