<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;

/**
 * Prevent N+1 Query Problems Trait
 *
 * Usage:
 * - Add this trait to your models
 * - Use withEagerLoad() to automatically load common relationships
 * - Use detectNPlusOne() to identify N+1 query problems
 */
trait PreventsNPlusOne
{
    /**
     * Scope to automatically eager load common relationships
     */
    public function scopeWithEagerLoad(Builder $query, array $relationships = null): Builder
    {
        if ($relationships === null) {
            $relationships = $this->getDefaultEagerLoads();
        }

        return $query->with($relationships);
    }

    /**
     * Get default relationships to eager load
     */
    protected function getDefaultEagerLoads(): array
    {
        // Override in models to define default eager loads
        return [];
    }

    /**
     * Detect and log N+1 query problems
     */
    public static function detectNPlusOne(callable $callback, bool $log = true): array
    {
        \DB::enableQueryLog();

        $callback();

        $queries = \DB::getQueryLog();
        $problems = static::analyzeNPlusOneProblems($queries);

        if ($log && !empty($problems)) {
            Log::warning('N+1 query problems detected', [
                'model' => get_class(new static()),
                'problems' => $problems,
            ]);
        }

        \DB::flushQueryLog();

        return $problems;
    }

    /**
     * Analyze queries for N+1 patterns
     */
    protected static function analyzeNPlusOneProblems(array $queries): array
    {
        $problems = [];
        $queryPatterns = [];

        foreach ($queries as $query) {
            $sql = $query['query'];

            // Normalize query
            $normalized = preg_replace([
                '/\d+/',
                '/\'[^\']*\'/',
                '/`?(\w+)`?\.`?(\w+)`?/',
            ], ['?', "'?", '$1.$2'], $sql);

            $pattern = md5($normalized);

            if (!isset($queryPatterns[$pattern])) {
                $queryPatterns[$pattern] = [
                    'sql' => $normalized,
                    'count' => 0,
                    'time' => 0,
                ];
            }

            $queryPatterns[$pattern]['count']++;
            $queryPatterns[$pattern]['time'] += $query['time'] ?? 0;
        }

        // Identify potential N+1 problems
        foreach ($queryPatterns as $pattern) {
            if ($pattern['count'] > 3) {
                $problems[] = [
                    'sql' => $pattern['sql'],
                    'count' => $pattern['count'],
                    'total_time' => $pattern['time'],
                    'avg_time' => $pattern['time'] / $pattern['count'],
                ];
            }
        }

        return $problems;
    }

    /**
     * Eager load relationships with count
     */
    public function scopeWithCounts(Builder $query, array $relations): Builder
    {
        return $query->withCount($relations);
    }

    /**
     * Lazy eager load relationships to prevent N+1
     */
    public function loadPreventNPlusOne(array $relations): self
    {
        $this->load($relations);
        return $this;
    }

    /**
     * Chunk results with optimized queries
     */
    public static function chunkWithEagerLoading(int $count, callable $callback, array $relations = []): void
    {
        static::with($relations)
            ->chunk($count, function ($items) use ($callback) {
                $callback($items);
            });
    }

    /**
     * Optimize query with cursor
     */
    public static function cursorOptimized(callable $callback, array $relations = []): void
    {
        $query = static::with($relations);

        foreach ($query->cursor() as $model) {
            $callback($model);
        }
    }

    /**
     * Prevent N+1 on collection iteration
     */
    public static function preventNPlusOneOnCollection(iterable $models, array $relations): iterable
    {
        if ($models instanceof \Illuminate\Database\Eloquent\Collection) {
            return $models->load($relations);
        }

        return collect($models)->load($relations);
    }
}
