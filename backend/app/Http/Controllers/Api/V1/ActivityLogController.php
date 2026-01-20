<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    /**
     * Get all activity logs with filters.
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user')
            ->orderBy('created_at', 'desc');

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by model
        if ($request->has('model_type')) {
            $query->where('model_type', $request->model_type);
        }

        if ($request->has('model_id')) {
            $query->where('model_id', $request->model_id);
        }

        // Filter by tag
        if ($request->has('tag')) {
            $query->withTag($request->tag);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->has('to_date')) {
            $query->dateRange($request->from_date, $request->to_date);
        }

        // Search in description
        if ($request->has('search')) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $logs = $query->paginate($request->per_page ?? 50);

        return response()->json($logs);
    }

    /**
     * Get a specific activity log.
     */
    public function show($id)
    {
        $log = ActivityLog::with(['user', 'model'])->findOrFail($id);

        return response()->json($log);
    }

    /**
     * Get activity statistics.
     */
    public function stats()
    {
        $totalLogs = ActivityLog::count();
        $todayLogs = ActivityLog::whereDate('created_at', today())->count();
        $weekLogs = ActivityLog::recent(7)->count();
        $monthLogs = ActivityLog::recent(30)->count();

        // Top actions
        $topActions = ActivityLog::select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Top users
        $topUsers = ActivityLog::select('user_id', DB::raw('count(*) as count'))
            ->with('user')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        // Recent activity timeline
        $recentActivity = ActivityLog::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            });

        // Security events
        $securityEvents = ActivityLog::security()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'total_logs' => $totalLogs,
            'today_logs' => $todayLogs,
            'week_logs' => $weekLogs,
            'month_logs' => $monthLogs,
            'top_actions' => $topActions,
            'top_users' => $topUsers,
            'recent_activity' => $recentActivity,
            'security_events' => $securityEvents,
        ]);
    }

    /**
     * Export activity logs.
     */
    public function export(Request $request)
    {
        $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

        // Apply same filters as index
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('from_date') && $request->has('to_date')) {
            $query->dateRange($request->from_date, $request->to_date);
        }

        $logs = $query->limit(10000)->get();

        // Generate CSV
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="activity-logs-' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            // CSV Header
            fputcsv($file, [
                'ID',
                'Date',
                'User',
                'Email',
                'Action',
                'Model',
                'Model ID',
                'Description',
                'IP Address',
                'Tags',
            ]);

            // CSV Rows
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'System',
                    $log->user?->email ?? '-',
                    $log->action,
                    $log->model_type ?? '-',
                    $log->model_id ?? '-',
                    $log->description ?? '-',
                    $log->ip_address ?? '-',
                    $log->tags ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Clean old activity logs (retention policy).
     */
    public function clean(Request $request)
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:30|max:365',
        ]);

        $days = $validated['days'] ?? 90; // Default 90 days

        $deleted = ActivityLog::where('created_at', '<', now()->subDays($days))->delete();

        return response()->json([
            'message' => "Deleted {$deleted} old activity logs (older than {$days} days)",
            'deleted_count' => $deleted,
        ]);
    }
}
