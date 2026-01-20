<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchQuery extends Model
{
    use HasFactory;

    protected $fillable = [
        'query',
        'results_count',
        'searched_by',
    ];

    protected $casts = [
        'results_count' => 'integer',
    ];

    public function searchedBy()
    {
        return $this->belongsTo(User::class, 'searched_by');
    }

    public function scopePopular($query)
    {
        return $query->selectRaw('query, COUNT(*) as count')
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit(10);
    }

    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at')->limit(20);
    }
}
