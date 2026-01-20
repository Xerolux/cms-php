<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'author',
        'description',
        'path',
        'config',
        'is_active',
        'installed_by',
        'installed_at',
    ];

    protected $casts = [
        'config' => 'array',
        'is_active' => 'boolean',
        'installed_at' => 'datetime',
    ];

    public function installer()
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function hooks()
    {
        return $this->hasMany(PluginHook::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
}
