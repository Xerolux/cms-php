<?php

namespace App\GraphQL\Mutations;

use App\Models\Comment;
use Illuminate\Support\Facades\Auth;

class CommentMutations
{
    /**
     * Moderate a comment (approve/reject/spam)
     */
    public function moderate($root, array $args)
    {
        $comment = Comment::findOrFail($args['id']);

        $this->authorize('moderate', $comment);

        $validStatuses = ['approved', 'rejected', 'spam', 'pending'];
        if (!in_array($args['status'], $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
        }

        $comment->update([
            'status' => $args['status'],
        ]);

        return $comment->fresh();
    }

    /**
     * Authorize the request
     */
    protected function authorize($ability, $comment)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            throw new \Exception('Unauthenticated');
        }

        // Only admins and editors can moderate comments
        if (!in_array($user->role, ['admin', 'editor'])) {
            throw new \Exception('Unauthorized');
        }
    }
}
