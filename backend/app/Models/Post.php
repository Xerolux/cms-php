<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'excerpt',
        'featured_image_id',
        'author_id',
        'status',
        'is_hidden',
        'published_at',
        'view_count',
        'meta_title',
        'meta_description',
        'language',
        'translation_of_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'view_count' => 'integer',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function featuredImage()
    {
        return $this->belongsTo(Media::class, 'featured_image_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'post_categories');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags');
    }

    public function downloads()
    {
        return $this->belongsToMany(Download::class, 'post_downloads');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeHidden($query)
    {
        return $query->where('is_hidden', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false)->whereIn('status', ['published']);
    }

    public function getIsScheduledAttribute(): bool
    {
        return $this->status === 'scheduled' && $this->published_at && $this->published_at->isFuture();
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === 'published' || ($this->status === 'scheduled' && $this->published_at && $this->published_at->isPast());
    }

    public function getIsHiddenAttribute(): bool
    {
        return (bool) $this->is_hidden;
    }
}
