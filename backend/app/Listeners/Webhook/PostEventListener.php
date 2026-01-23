<?php

namespace App\Listeners\Webhook;

use App\Models\Post;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;

class PostEventListener
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle post created event.
     */
    public function onPostCreated(Post $post): void
    {
        try {
            $this->webhookService->dispatch('post.created', $this->transformPost($post));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch post.created webhook', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle post updated event.
     */
    public function onPostUpdated(Post $post): void
    {
        try {
            $this->webhookService->dispatch('post.updated', $this->transformPost($post));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch post.updated webhook', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle post published event.
     */
    public function onPostPublished(Post $post): void
    {
        try {
            $this->webhookService->dispatch('post.published', $this->transformPost($post));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch post.published webhook', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle post deleted event.
     */
    public function onPostDeleted(Post $post): void
    {
        try {
            $this->webhookService->dispatch('post.deleted', [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch post.deleted webhook', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle post scheduled event.
     */
    public function onPostScheduled(Post $post): void
    {
        try {
            $this->webhookService->dispatch('post.scheduled', $this->transformPost($post));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch post.scheduled webhook', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Transform post for webhook payload.
     */
    protected function transformPost(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'status' => $post->status,
            'is_hidden' => (bool) $post->is_hidden,
            'published_at' => $post->published_at?->toIso8601String(),
            'author' => [
                'id' => $post->author?->id,
                'name' => $post->author?->name,
                'email' => $post->author?->email,
            ],
            'categories' => $post->categories->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
            ]),
            'tags' => $post->tags->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ]),
            'featured_image' => $post->featuredImage ? [
                'id' => $post->featuredImage->id,
                'url' => $post->featuredImage->url,
                'thumbnail_url' => $post->featuredImage->thumbnail_url,
            ] : null,
            'language' => $post->language,
            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,
            'view_count' => $post->view_count,
            'url' => $post->getFullUrl(),
            'created_at' => $post->created_at->toIso8601String(),
            'updated_at' => $post->updated_at->toIso8601String(),
        ];
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            'post.created' => 'onPostCreated',
            'post.updated' => 'onPostUpdated',
            'post.published' => 'onPostPublished',
            'post.deleted' => 'onPostDeleted',
            'post.scheduled' => 'onPostScheduled',
        ];
    }
}
