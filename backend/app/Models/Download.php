<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Download extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'original_filename',
        'filepath',
        'mime_type',
        'filesize',
        'description',
        'access_level',
        'download_count',
        'uploaded_by',
        'expires_at',
    ];

    protected $casts = [
        'filesize' => 'integer',
        'download_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_downloads');
    }

    public function tokens()
    {
        return $this->hasMany(DownloadToken::class);
    }

    public function generateToken($userId = null)
    {
        return $this->tokens()->create([
            'token' => Str::random(64),
            'user_id' => $userId,
            'expires_at' => now()->addHour(),
        ]);
    }

    public function incrementDownloadCount()
    {
        $this->increment('download_count');
    }
}
