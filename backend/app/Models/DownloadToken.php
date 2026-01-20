<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownloadToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'download_id',
        'user_id',
        'ip_address',
        'expires_at',
        'used_at',
        'is_valid',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'is_valid' => 'boolean',
    ];

    public function download()
    {
        return $this->belongsTo(Download::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isValid()
    {
        return $this->is_valid && $this->expires_at > now() && !$this->used_at;
    }

    public function markAsUsed()
    {
        $this->update([
            'used_at' => now(),
            'is_valid' => false,
        ]);
    }
}
