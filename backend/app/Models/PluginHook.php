<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PluginHook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hook',
        'description',
        'plugin_id',
        'priority',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    public function plugin()
    {
        return $this->belongsTo(Plugin::class);
    }

    public function scopeByHook($query, string $hook)
    {
        return $query->where('hook', $hook)->orderBy('priority');
    }
}
