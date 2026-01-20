<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Process;

class SystemHealthController extends Controller
{
    /**
     * Get complete system health information.
     */
    public function index()
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'uptime' => $this->getUptime(),
            'environment' => $this->getEnvironmentInfo(),
            'server' => $this->getServerInfo(),
            'database' => $this->getDatabaseInfo(),
            'cache' => $this->getCacheInfo(),
            'storage' => $this->getStorageInfo(),
            'services' => $this->getServicesStatus(),
            'php' => $this->getPhpInfo(),
            'laravel' => $this->getLaravelInfo(),
        ];

        // Determine overall status
        $services = $health['services'];
        $criticalFailures = collect($services)->filter(fn ($s) => $s['status'] === 'critical')->count();
        $warnings = collect($services)->filter(fn ($s) => $s['status'] === 'warning')->count();

        if ($criticalFailures > 0) {
            $health['status'] = 'critical';
        } elseif ($warnings > 0) {
            $health['status'] = 'warning';
        }

        return response()->json($health);
    }

    /**
     * Get server uptime.
     */
    protected function getUptime(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int) explode('.', $uptime)[0];
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $minutes = floor(($seconds % 3600) / 60);

                return sprintf('%dd %dh %dm', $days, $hours, $minutes);
            }
        }

        return 'Unknown';
    }

    /**
     * Get environment information.
     */
    protected function getEnvironmentInfo(): array
    {
        return [
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
        ];
    }

    /**
     * Get server information.
     */
    protected function getServerInfo(): array
    {
        return [
            'os' => PHP_OS,
            'os_family' => PHP_OS_FAMILY,
            'hostname' => gethostname(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_sapi' => php_sapi_name(),
        ];
    }

    /**
     * Get database information.
     */
    protected function getDatabaseInfo(): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();

            // Get database version
            $version = $connection->select('SELECT VERSION() as version')[0]->version ?? 'Unknown';

            // Get database size (MySQL)
            $size = null;
            if ($connection->getDriverName() === 'mysql') {
                $dbName = config('database.connections.mysql.database');
                $sizeResult = $connection->select("
                    SELECT
                        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                    FROM information_schema.TABLES
                    WHERE table_schema = '{$dbName}'
                ");
                $size = $sizeResult[0]->size_mb ?? 0;
            }

            // Test connection with a simple query
            $connection->select('SELECT 1');

            return [
                'status' => 'connected',
                'connection' => $connection->getDriverName(),
                'version' => $version,
                'database' => config('database.connections.mysql.database'),
                'size_mb' => $size,
                'max_connections' => config('database.connections.mysql.max_connections') ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cache information.
     */
    protected function getCacheInfo(): array
    {
        try {
            $defaultStore = config('cache.default');
            $stores = config('cache.stores');

            $info = [
                'default' => $default,
                'stores' => [],
            ];

            foreach ($stores as $name => $config) {
                $storeInfo = [
                    'driver' => $config['driver'] ?? 'file',
                    'status' => 'unknown',
                ];

                // Test Redis connection
                if ($config['driver'] === 'redis') {
                    try {
                        Redis::connection($name === 'default' ? null : $name)->ping();
                        $storeInfo['status'] = 'connected';
                        $storeInfo['info'] = Redis::connection()->info();
                    } catch (\Exception $e) {
                        $storeInfo['status'] = 'error';
                        $storeInfo['error'] = $e->getMessage();
                    }
                } elseif ($config['driver'] === 'file') {
                    $storeInfo['path'] = $config['path'] ?? storage_path('framework/cache/data');
                    $storeInfo['status'] = 'available';
                } else {
                    $storeInfo['status'] = 'available';
                }

                $info['stores'][$name] = $storeInfo;
            }

            return $info;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get storage information.
     */
    protected function getStorageInfo(): array
    {
        $disks = [];
        $diskConfig = config('filesystems.disks');

        foreach ($diskConfig as $name => $config) {
            try {
                $disk = Storage::disk($name);

                // Test if disk is working
                $files = $disk->files('/');
                $diskInfo = [
                    'driver' => $config['driver'] ?? 'local',
                    'status' => 'available',
                    'root' => $config['root'] ?? null,
                ];

                // Get disk usage (local only)
                if ($config['driver'] === 'local') {
                    $total = disk_total_space($config['root']);
                    $free = disk_free_space($config['root']);
                    $used = $total - $free;

                    $diskInfo['usage'] = [
                        'total_gb' => round($total / 1024 / 1024 / 1024, 2),
                        'used_gb' => round($used / 1024 / 1024 / 1024, 2),
                        'free_gb' => round($free / 1024 / 1024 / 1024, 2),
                        'usage_percent' => round(($used / $total) * 100, 2),
                    ];
                }

                $disks[$name] = $diskInfo;
            } catch (\Exception $e) {
                $disks[$name] = [
                    'driver' => $config['driver'] ?? 'unknown',
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $disks;
    }

    /**
     * Get services status.
     */
    protected function getServicesStatus(): array
    {
        $services = [];

        // Database
        try {
            DB::select('SELECT 1');
            $services['database'] = [
                'name' => 'Database',
                'status' => 'ok',
                'message' => 'Connected',
            ];
        } catch (\Exception $e) {
            $services['database'] = [
                'name' => 'Database',
                'status' => 'critical',
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }

        // Cache (Redis if available)
        if (config('cache.default') === 'redis') {
            try {
                Redis::ping();
                $services['redis'] = [
                    'name' => 'Redis',
                    'status' => 'ok',
                    'message' => 'Connected',
                ];
            } catch (\Exception $e) {
                $services['redis'] = [
                    'name' => 'Redis',
                    'status' => 'critical',
                    'message' => 'Connection failed: ' . $e->getMessage(),
                ];
            }
        } else {
            $services['cache'] = [
                'name' => 'Cache',
                'status' => 'ok',
                'message' => 'File cache available',
            ];
        }

        // Storage
        try {
            Storage::disk('local')->put('health_check.tmp', 'test');
            Storage::disk('local')->delete('health_check.tmp');
            $services['storage'] = [
                'name' => 'Storage',
                'status' => 'ok',
                'message' => 'Working',
            ];
        } catch (\Exception $e) {
            $services['storage'] = [
                'name' => 'Storage',
                'status' => 'critical',
                'message' => 'Write failed: ' . $e->getMessage(),
            ];
        }

        // Scheduled Tasks (last run time from cache)
        $lastCron = cache('last_cron_run');
        if ($lastCron) {
            $minutes = now()->diffInMinutes($lastCron);
            if ($minutes > 5) {
                $services['cron'] = [
                    'name' => 'Scheduled Tasks',
                    'status' => 'warning',
                    'message' => "Last run {$minutes} minutes ago",
                ];
            } else {
                $services['cron'] = [
                    'name' => 'Scheduled Tasks',
                    'status' => 'ok',
                    'message' => "Running (last: {$minutes} min ago)",
                ];
            }
        } else {
            $services['cron'] = [
                'name' => 'Scheduled Tasks',
                'status' => 'warning',
                'message' => 'Not configured or never run',
            ];
        }

        // Queue (if configured)
        if (config('queue.default') !== 'sync') {
            $queueSize = 0;
            try {
                $queueSize = DB::table('jobs')->count();
                $services['queue'] = [
                    'name' => 'Queue',
                    'status' => 'ok',
                    'message' => "{$queueSize} jobs pending",
                ];
            } catch (\Exception $e) {
                $services['queue'] = [
                    'name' => 'Queue',
                    'status' => 'warning',
                    'message' => 'Database queue not configured',
                ];
            }
        }

        return $services;
    }

    /**
     * Get PHP information.
     */
    protected function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'extensions' => get_loaded_extensions(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'opcache' => function_exists('opcache_get_status') ? 'enabled' : 'disabled',
        ];
    }

    /**
     * Get Laravel information.
     */
    protected function getLaravelInfo(): array
    {
        return [
            'version' => app()->version(),
            'locale' => app()->getLocale(),
            'environment' => app()->environment(),
        ];
    }

    /**
     * Simple health check for load balancers.
     */
    public function ping()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
