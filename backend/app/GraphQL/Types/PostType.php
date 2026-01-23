<?php

namespace App\GraphQL\Types;

use App\Models\Post;
use GraphQL\Type\Definition\Type;
use Rebing\GraphQL\Support\Type as GraphQLType;

class PostType extends GraphQLType
{
    /**
     * Check if post is scheduled
     */
    public function isScheduled($root): bool
    {
        return $root->status === 'scheduled'
            && $root->published_at
            && $root->published_at->isFuture();
    }

    /**
     * Check if post is published
     */
    public function isPublished($root): bool
    {
        return $root->status === 'published'
            || ($root->status === 'scheduled' && $root->published_at && $root->published_at->isPast());
    }

    /**
     * Get full URL for the post
     */
    public function fullUrl($root): string
    {
        $localePrefix = $root->language && $root->language !== config('app.locale')
            ? '/' . $root->language
            : '';

        return url($localePrefix . '/blog/' . $root->slug);
    }
}
