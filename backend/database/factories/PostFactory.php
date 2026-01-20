<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence(rand(3, 8));
        return [
            'title' => $title,
            'slug' => Str::slug($title) . '-' . fake()->unique()->randomNumber(5),
            'content' => fake()->paragraphs(rand(5, 15), true),
            'excerpt' => fake()->sentence(),
            'author_id' => User::factory(),
            'status' => fake()->randomElement(['draft', 'published', 'scheduled']),
            'is_hidden' => false,
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'view_count' => fake()->numberBetween(0, 10000),
            'meta_title' => $title,
            'meta_description' => fake()->sentence(),
            'language' => 'de',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'published_at' => fake()->dateTimeBetween('now', '+1 month'),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
        ]);
    }
}
