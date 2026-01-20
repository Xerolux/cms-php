<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'author_name',
        'author_email',
        'author_ip',
        'content',
        'status',
        'likes_count',
        'dislikes_count',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'likes_count' => 'integer',
        'dislikes_count' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $with = ['user', 'parent'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSpam($query)
    {
        return $query->where('status', 'spam');
    }

    public function scopeForPost($query, $postId)
    {
        return $query->where('post_id', $postId);
    }

    public function approve()
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function reject()
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
        ]);
    }

    public function markAsSpam()
    {
        $this->update(['status' => 'spam']);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function getAuthorNameAttribute(): string
    {
        if ($this->user) {
            return $this->user->display_name ?? $this->user->name;
        }

        return $this->author_name;
    }

    public function getAuthorEmailAttribute(): ?string
    {
        if ($this->user) {
            return $this->user->email;
        }

        return $this->author_email;
    }
}
