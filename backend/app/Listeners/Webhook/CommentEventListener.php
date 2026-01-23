<?php

namespace App\Listeners\Webhook;

use App\Models\Comment;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;

class CommentEventListener
{
    protected WebhookService $webhookService;

    public function __construct(WebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle comment created event.
     */
    public function onCommentCreated(Comment $comment): void
    {
        try {
            $this->webhookService->dispatch('comment.created', $this->transformComment($comment));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch comment.created webhook', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle comment updated event.
     */
    public function onCommentUpdated(Comment $comment): void
    {
        try {
            $this->webhookService->dispatch('comment.updated', $this->transformComment($comment));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch comment.updated webhook', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle comment deleted event.
     */
    public function onCommentDeleted(Comment $comment): void
    {
        try {
            $this->webhookService->dispatch('comment.deleted', [
                'id' => $comment->id,
                'post_id' => $comment->post_id,
                'author_name' => $comment->author_name,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch comment.deleted webhook', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle comment approved event.
     */
    public function onCommentApproved(Comment $comment): void
    {
        try {
            $this->webhookService->dispatch('comment.approved', $this->transformComment($comment));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch comment.approved webhook', [
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Transform comment for webhook payload.
     */
    protected function transformComment(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'post_title' => $comment->post?->title,
            'content' => $comment->content,
            'author_name' => $comment->author_name,
            'author_email' => $comment->author_email,
            'author_url' => $comment->author_url,
            'user_id' => $comment->user_id,
            'parent_id' => $comment->parent_id,
            'status' => $comment->status,
            'ip_address' => $comment->ip_address,
            'user_agent' => $comment->user_agent,
            'created_at' => $comment->created_at->toIso8601String(),
            'updated_at' => $comment->updated_at->toIso8601String(),
        ];
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            'comment.created' => 'onCommentCreated',
            'comment.updated' => 'onCommentUpdated',
            'comment.deleted' => 'onCommentDeleted',
            'comment.approved' => 'onCommentApproved',
        ];
    }
}
