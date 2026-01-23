<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Predis\Client as PredisClient;

/**
 * Redis Cluster Service
 *
 * Features:
 * - Master-Slave replication
 * - Automatic failover
 * - Read/write splitting
 * - Connection pooling
 */
class RedisClusterService
{
    private ?PredisClient $master = null;
    private ?PredisClient $slave = null;
    private array $config;
    private bool $useReplication = true;
    private int $retryAttempts = 3;
    private int $retryDelay = 100; // milliseconds

    public function __construct()
    {
        $this->config = [
            'master' => [
                'host' => config('cache.redis.master.host', 'redis-master'),
                'port' => config('cache.redis.master.port', 6379),
                'password' => config('cache.redis.master.password', null),
                'database' => config('cache.redis.master.database', 0),
            ],
            'slave' => [
                'host' => config('cache.redis.slave.host', 'redis-slave'),
                'port' => config('cache.redis.slave.port', 6380),
                'password' => config('cache.redis.slave.password', null),
                'database' => config('cache.redis.slave.database', 0),
            ],
        ];

        $this->useReplication = config('cache.redis.replication', true);
        $this->initializeConnections();
    }

    /**
     * Initialize Redis connections
     */
    private function initializeConnections(): void
    {
        try {
            // Initialize master connection
            $this->master = new PredisClient([
                'scheme' => 'tcp',
                'host' => $this->config['master']['host'],
                'port' => $this->config['master']['port'],
                'password' => $this->config['master']['password'],
                'database' => $this->config['master']['database'],
                'read_write_timeout' => 0,
                'persistent' => true,
            ]);

            // Initialize slave connection if replication is enabled
            if ($this->useReplication) {
                $this->slave = new PredisClient([
                    'scheme' => 'tcp',
                    'host' => $this->config['slave']['host'],
                    'port' => $this->config['slave']['port'],
                    'password' => $this->config['slave']['password'],
                    'database' => $this->config['slave']['database'],
                    'read_write_timeout' => 0,
                    'persistent' => true,
                ]);
            }

            Log::info('Redis cluster connections initialized');
        } catch (\Exception $e) {
            Log::error('Failed to initialize Redis cluster connections', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get connection for read operations
     */
    public function getReadConnection(): PredisClient
    {
        if ($this->useReplication && $this->slave && $this->isSlaveHealthy()) {
            return $this->slave;
        }

        return $this->master;
    }

    /**
     * Get connection for write operations
     */
    public function getWriteConnection(): PredisClient
    {
        return $this->master;
    }

    /**
     * Check if slave is healthy
     */
    private function isSlaveHealthy(): bool
    {
        if (!$this->slave) {
            return false;
        }

        try {
            $this->slave->ping();
            return true;
        } catch (\Exception $e) {
            Log::warning('Redis slave is unhealthy, failing over to master', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if master is healthy
     */
    private function isMasterHealthy(): bool
    {
        if (!$this->master) {
            return false;
        }

        try {
            $this->master->ping();
            return true;
        } catch (\Exception $e) {
            Log::error('Redis master is unhealthy', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get value from cache
     */
    public function get(string $key): mixed
    {
        return $this->withRetry(function () use ($key) {
            $connection = $this->getReadConnection();
            $value = $connection->get($key);

            return $value !== null ? unserialize($value) : null;
        });
    }

    /**
     * Set value in cache
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->withRetry(function () use ($key, $value, $ttl) {
            $connection = $this->getWriteConnection();

            if ($ttl > 0) {
                return $connection->setex($key, $ttl, serialize($value));
            }

            return $connection->set($key, serialize($value));
        });
    }

    /**
     * Delete key from cache
     */
    public function delete(string $key): bool
    {
        return $this->withRetry(function () use ($key) {
            return $this->getWriteConnection()->del($key) > 0;
        });
    }

    /**
     * Delete multiple keys
     */
    public function deleteMultiple(array $keys): int
    {
        return $this->withRetry(function () use ($keys) {
            return $this->getWriteConnection()->del($keys);
        });
    }

    /**
     * Check if key exists
     */
    public function exists(string $key): bool
    {
        return $this->withRetry(function () use ($key) {
            return $this->getReadConnection()->exists($key) > 0;
        });
    }

    /**
     * Set TTL for key
     */
    public function expire(string $key, int $ttl): bool
    {
        return $this->withRetry(function () use ($key, $ttl) {
            return $this->getWriteConnection()->expire($key, $ttl);
        });
    }

    /**
     * Get TTL for key
     */
    public function ttl(string $key): int
    {
        return $this->withRetry(function () use ($key) {
            return $this->getReadConnection()->ttl($key);
        });
    }

    /**
     * Increment value
     */
    public function increment(string $key, int $value = 1): int
    {
        return $this->withRetry(function () use ($key, $value) {
            return $this->getWriteConnection()->incrby($key, $value);
        });
    }

    /**
     * Decrement value
     */
    public function decrement(string $key, int $value = 1): int
    {
        return $this->withRetry(function () use ($key, $value) {
            return $this->getWriteConnection()->decrby($key, $value);
        });
    }

    /**
     * Add to set
     */
    public function sadd(string $key, string $value): int
    {
        return $this->withRetry(function () use ($key, $value) {
            return $this->getWriteConnection()->sadd($key, $value);
        });
    }

    /**
     * Remove from set
     */
    public function srem(string $key, string $value): int
    {
        return $this->withRetry(function () use ($key, $value) {
            return $this->getWriteConnection()->srem($key, $value);
        });
    }

    /**
     * Get all set members
     */
    public function smembers(string $key): array
    {
        return $this->withRetry(function () use ($key) {
            return $this->getReadConnection()->smembers($key);
        });
    }

    /**
     * Add to sorted set
     */
    public function zadd(string $key, float $score, string $value): int
    {
        return $this->withRetry(function () use ($key, $score, $value) {
            return $this->getWriteConnection()->zadd($key, $score, $value);
        });
    }

    /**
     * Get range from sorted set
     */
    public function zrange(string $key, int $start, int $stop, bool $withScores = false): array
    {
        return $this->withRetry(function () use ($key, $start, $stop, $withScores) {
            $options = $withScores ? ['withscores' => true] : [];
            return $this->getReadConnection()->zrange($key, $start, $stop, $options);
        });
    }

    /**
     * Flush all databases
     */
    public function flush(): bool
    {
        return $this->withRetry(function () {
            return $this->getWriteConnection()->flushdb();
        });
    }

    /**
     * Get cluster information
     */
    public function getClusterInfo(): array
    {
        $info = [
            'replication_enabled' => $this->useReplication,
            'master' => [
                'healthy' => $this->isMasterHealthy(),
                'host' => $this->config['master']['host'],
                'port' => $this->config['master']['port'],
            ],
            'slave' => [
                'healthy' => $this->useReplication && $this->isSlaveHealthy(),
                'host' => $this->config['slave']['host'],
                'port' => $this->config['slave']['port'],
            ],
        ];

        // Get master info
        if ($info['master']['healthy']) {
            try {
                $info['master']['info'] = $this->master->info();
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Get slave info
        if ($info['slave']['healthy']) {
            try {
                $info['slave']['info'] = $this->slave->info();
            } catch (\Exception $e) {
                // Ignore
            }
        }

        return $info;
    }

    /**
     * Execute operation with retry logic
     */
    private function withRetry(callable $callback): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->retryAttempts; $attempt++) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt < $this->retryAttempts) {
                    usleep($this->retryDelay * 1000);

                    // Try to reinitialize connections
                    if (!$this->isMasterHealthy()) {
                        $this->initializeConnections();
                    }
                }
            }
        }

        throw $lastException;
    }

    /**
     * Promote slave to master (failover)
     */
    public function promoteSlaveToMaster(): bool
    {
        try {
            if ($this->slave && $this->isSlaveHealthy()) {
                Log::info('Promoting slave to master');

                // In production, this would involve more complex logic
                // such as updating DNS, notifying other services, etc.

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to promote slave to master', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get connection statistics
     */
    public function getConnectionStats(): array
    {
        return [
            'master_connected' => $this->master !== null && $this->isMasterHealthy(),
            'slave_connected' => $this->slave !== null && $this->isSlaveHealthy(),
            'replication_enabled' => $this->useReplication,
            'config' => $this->config,
        ];
    }

    /**
     * Execute pipeline commands
     */
    public function pipeline(callable $callback): array
    {
        return $this->withRetry(function () use ($callback) {
            $connection = $this->getWriteConnection();
            $pipeline = $connection->pipeline();

            $callback($pipeline);

            return $pipeline->execute();
        });
    }

    /**
     * Execute transaction
     */
    public function transaction(callable $callback): array
    {
        return $this->withRetry(function () use ($callback) {
            $connection = $this->getWriteConnection();
            $transaction = $connection->multi();

            $callback($transaction);

            return $transaction->exec();
        });
    }
}
