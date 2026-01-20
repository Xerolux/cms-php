<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'template',
        'meta_title',
        'meta_description',
        'is_visible',
        'is_in_menu',
        'menu_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'is_in_menu' => 'boolean',
        'menu_order' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeInMenu($query)
    {
        return $query->where('is_in_menu', true)->orderBy('menu_order');
    }

    public function setSlugAttribute($value)
    {
        $this->attributes['slug'] = str_slug($value, '-');
    }
}
