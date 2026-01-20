<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_filename',
        'filepath',
        'url',
        'mime_type',
        'filesize',
        'width',
        'height',
        'alt_text',
        'caption',
        'thumbnails',
        'webp_url',
        'uploaded_by',
    ];

    protected $casts = [
        'filesize' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'thumbnails' => 'array',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'featured_image_id');
    }
}
