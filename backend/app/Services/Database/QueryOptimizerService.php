<?php

namespace App\Services\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Database Query Optimization Service
 *
 * Features:
 * - Index analysis and optimization
 * - Query caching
 * - N+1 query prevention
 * - Slow query detection and logging
 */
class QueryOptimizerService
{
    private array $slowQueries = [];
    private float $slowQueryThreshold = 0.1; // 100ms
    private bool $loggingEnabled = true;
    private array $queryCache = [];
    private int $cacheHits = 0;
    private int $cacheMisses = 0;

    /**
     * Analyze database indexes for a table
     */
    public function analyzeIndexes(string $table): array
    {
        $indexes = DB::select("SHOW INDEX FROM {$table}");
        $columns = DB::select("SHOW COLUMNS FROM {$table}");

        $analysis = [
            'table' => $table,
            'indexes' => [],
            'recommendations' => [],
        ];

        foreach ($indexes as $index) {
            $analysis['indexes'][] = [
                'name' => $index->Key_name,
                'column' => $index->Column_name,
                'unique' => $index->Non_unique === 0,
                'type' => $index->Index_type,
                'cardinality' => $index->Cardinality,
            ];
        }

        // Generate recommendations
        $analysis['recommendations'] = $this->generateIndexRecommendations($table, $analysis['indexes'], $columns);

        return $analysis;
    }

    /**
     * Generate index optimization recommendations
     */
    private function generateIndexRecommendations(string $table, array $indexes, array $columns): array
    {
        $recommendations = [];
        $indexedColumns = collect($indexes)->pluck('column')->unique()->toArray();

        // Check for missing indexes on foreign keys
        foreach ($columns as $column) {
            if (str_ends_with($column->Field, '_id') && !in_array($column->Field, $indexedColumns)) {
                $recommendations[] = [
                    'type' => 'add_index',
                    'priority' => 'high',
                    'message' => "Add index on foreign key column: {$column->Field}",
                    'sql' => "CREATE INDEX idx_{$column->Field} ON {$table}({$column->Field})",
                ];
            }
        }

        // Check for missing indexes on frequently queried columns
        $queryPatterns = [
            'created_at' => 'date_range',
            'updated_at' => 'date_range',
            'published_at' => 'date_range',
            'slug' => 'lookup',
            'email' => 'lookup',
            'status' => 'filter',
        ];

        foreach ($queryPatterns as $column => $pattern) {
            $columnExists = collect($columns)->contains('Field', $column);
            $isIndexed = in_array($column, $indexedColumns);

            if ($columnExists && !$isIndexed) {
                $recommendations[] = [
                    'type' => 'add_index',
                    'priority' => 'medium',
                    'message' => "Consider adding index on {$column} for {$pattern} queries",
                    'sql' => "CREATE INDEX idx_{$column} ON {$table}({$column})",
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Create recommended index
     */
    public function createIndex(string $table, string $column, bool $unique = false): bool
    {
        try {
            $indexName = "idx_{$column}";
            $uniqueClause = $unique ? 'UNIQUE' : '';

            DB::statement("
                CREATE {$uniqueClause} INDEX {$indexName}
                ON {$table}({$column})
            ");

            Log::info("Created index {$indexName} on {$table}.{$column}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to create index on {$table}.{$column}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Drop index
     */
    public function dropIndex(string $table, string $indexName): bool
    {
        try {
            DB::statement("DROP INDEX {$indexName} ON {$table}");

            Log::info("Dropped index {$indexName} from {$table}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to drop index {$indexName} from {$table}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Optimize table
     */
    public function optimizeTable(string $table): bool
    {
        try {
            DB::statement("OPTIMIZE TABLE {$table}");

            Log::info("Optimized table {$table}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to optimize table {$table}", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Analyze query for N+1 problems
     */
    public function detectNPlusOneQueries(): array
    {
        $queries = DB::getQueryLog();
        $problems = [];

        if (empty($queries)) {
            return $problems;
        }

        // Group queries by table
        $queryGroups = [];
        foreach ($queries as $query) {
            preg_match('/from\s+`?(\w+)`?/i', $query['query'], $matches);
            $table = $matches[1] ?? 'unknown';

            if (!isset($queryGroups[$table])) {
                $queryGroups[$table] = [];
            }

            $queryGroups[$table][] = $query;
        }

        // Detect N+1 patterns
        foreach ($queryGroups as $table => $tableQueries) {
            if (count($tableQueries) > 5) {
                $similarQueries = $this->groupSimilarQueries($tableQueries);

                foreach ($similarQueries as $pattern => $count) {
                    if ($count > 3) {
                        $problems[] = [
                            'table' => $table,
                            'pattern' => $pattern,
                            'count' => $count,
                            'suggestion' => "Consider eager loading relationships for {$table} using with()",
                        ];
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * Group similar queries together
     */
    private function groupSimilarQueries(array $queries): array
    {
        $groups = [];

        foreach ($queries as $query) {
            // Normalize query pattern
            $pattern = preg_replace([
                '/\d+/',
                '/\'[^\']*\'/',
            ], ['?', "'?'"], $query['query']);

            if (!isset($groups[$pattern])) {
                $groups[$pattern] = 0;
            }

            $groups[$pattern]++;
        }

        return $groups;
    }

    /**
     * Enable query logging
     */
    public function enableQueryLogging(): void
    {
        DB::enableQueryLog();
    }

    /**
     * Get query log
     */
    public function getQueryLog(): array
    {
        return DB::getQueryLog();
    }

    /**
     * Log slow queries
     */
    public function logSlowQuery(string $sql, float $time, array $bindings = []): void
    {
        if ($time > $this->slowQueryThreshold) {
            $this->slowQueries[] = [
                'sql' => $sql,
                'time' => $time,
                'bindings' => $bindings,
                'timestamp' => now()->toIso8601String(),
            ];

            if ($this->loggingEnabled) {
                Log::warning('Slow query detected', [
                    'sql' => $sql,
                    'time' => $time,
                    'bindings' => $bindings,
                ]);
            }
        }
    }

    /**
     * Get slow queries
     */
    public function getSlowQueries(): array
    {
        return $this->slowQueries;
    }

    /**
     * Clear slow queries log
     */
    public function clearSlowQueries(): void
    {
        $this->slowQueries = [];
    }

    /**
     * Cache query result
     */
    public function cacheQuery(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $cacheKey = "query:{$key}";

        if (isset($this->queryCache[$cacheKey])) {
            $this->cacheHits++;
            return $this->queryCache[$cacheKey];
        }

        $this->cacheMisses++;
        $result = $callback();

        $this->queryCache[$cacheKey] = $result;

        // Store in Redis for persistence
        \Illuminate\Support\Facades\Cache::put($cacheKey, $result, $ttl);

        return $result;
    }

    /**
     * Invalidate query cache
     */
    public function invalidateQueryCache(string $pattern = null): void
    {
        if ($pattern) {
            foreach ($this->queryCache as $key => $value) {
                if (str_contains($key, $pattern)) {
                    unset($this->queryCache[$key]);
                }
            }

            // Also clear from Redis
            $redisKeys = \Illuminate\Support\Facades\Cache::getRedis()->keys("query:{$pattern}*");
            if (!empty($redisKeys)) {
                \Illuminate\Support\Facades\Cache::getRedis()->del($redisKeys);
            }
        } else {
            $this->queryCache = [];
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $total = $this->cacheHits + $this->cacheMisses;

        return [
            'hits' => $this->cacheHits,
            'misses' => $this->cacheMisses,
            'hit_rate' => $total > 0 ? round(($this->cacheHits / $total) * 100, 2) : 0,
            'size' => count($this->queryCache),
        ];
    }

    /**
     * Optimize query with hints
     */
    public function optimizeQuery(Model $model, string $query): \Illuminate\Database\Eloquent\Builder
    {
        // Add index hints if available
        $indexes = $this->analyzeIndexes($model->getTable());

        foreach ($indexes['recommendations'] as $recommendation) {
            if ($recommendation['type'] === 'add_index' && $recommendation['priority'] === 'high') {
                Log::info("Missing high-priority index detected", [
                    'table' => $model->getTable(),
                    'recommendation' => $recommendation,
                ]);
            }
        }

        return $model->query();
    }

    /**
     * Bulk insert with optimization
     */
    public function bulkInsert(string $table, array $data, int $chunkSize = 100): bool
    {
        try {
            // Disable query logging during bulk insert
            $loggingEnabled = DB::logging();
            DB::disableQueryLog();

            // Chunk data for memory efficiency
            collect($data)->chunk($chunkSize)->each(function ($chunk) use ($table) {
                DB::table($table)->insert($chunk->toArray());
            });

            // Re-enable query logging
            if ($loggingEnabled) {
                DB::enableQueryLog();
            }

            Log::info("Bulk insert completed", [
                'table' => $table,
                'count' => count($data),
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Bulk insert failed", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Explain query plan
     */
    public function explainQuery(string $sql, array $bindings = []): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();

            $statement = $pdo->prepare("EXPLAIN {$sql}");
            $statement->execute($bindings);

            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Log::error("Failed to explain query", [
                'sql' => $sql,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get table statistics
     */
    public function getTableStats(string $table): array
    {
        try {
            $rowCount = DB::table($table)->count();
            $size = DB::select("
                SELECT
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
                AND table_name = '{$table}'
            ");

            return [
                'table' => $table,
                'row_count' => $rowCount,
                'size_mb' => $size[0]->size_mb ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get table stats", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}
