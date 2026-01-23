<?php

namespace App\GraphQL\Mutations;

use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PostMutations
{
    /**
     * Submit a post for review
     */
    public function submitForReview($root, array $args)
    {
        $post = Post::findOrFail($args['id']);

        $this->authorize('update', $post);

        $post->update([
            'status' => 'pending_review',
            'submitted_for_review_at' => now(),
        ]);

        return $post->fresh();
    }

    /**
     * Approve a post
     */
    public function approvePost($root, array $args)
    {
        $post = Post::findOrFail($args['id']);

        $this->authorize('approve', $post);

        $post->update([
            'status' => 'published',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'reviewer_feedback' => $args['feedback'] ?? null,
            'published_at' => $post->published_at ?? now(),
        ]);

        return $post->fresh();
    }

    /**
     * Request changes for a post
     */
    public function requestChanges($root, array $args)
    {
        $post = Post::findOrFail($args['id']);

        $this->authorize('review', $post);

        $post->update([
            'status' => 'draft',
            'reviewer_feedback' => $args['feedback'],
            'changes_requested_at' => now(),
        ]);

        return $post->fresh();
    }

    /**
     * Authorize the request
     */
    protected function authorize($ability, $post)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        // Admins can do everything
        if ($user->role === 'admin') {
            return;
        }

        // Editors can approve and review
        if (in_array($ability, ['approve', 'review']) && $user->role !== 'editor') {
            throw new \Exception('Unauthorized');
        }

        // Authors can only update their own posts
        if ($ability === 'update' && $post->author_id !== $user->id) {
            throw new \Exception('Unauthorized');
        }
    }
}
