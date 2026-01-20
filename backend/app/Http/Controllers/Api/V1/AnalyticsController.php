<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PageView;
use App\Models\Post;
use App\Services\GeoIpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    protected GeoIpService $geoIpService;

    public function __construct(GeoIpService $geoIpService)
    {
        $this->geoIpService = $geoIpService;
    }

    /**
     * Track einen Page View
     */
    public function track(Request $request)
    {
        $validated = $request->validate([
            'post_id' => 'nullable|exists:posts,id',
            'page_url' => 'nullable|string|max:500',
        ]);

        $userAgent = $request->userAgent();
        // Use real IP for GeoIP lookup before anonymizing
        $realIp = $request->ip();
        $countryCode = $this->geoIpService->getCountryCode($realIp);

        $ipAddress = $this->anonymizeIp($realIp);

        $pageView = PageView::create([
            'post_id' => $validated['post_id'] ?? null,
            'page_url' => $validated['page_url'] ?? $request->headers->get('referer'),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer' => $request->headers->get('referer'),
            'country_code' => $countryCode,
            'device_type' => PageView::detectDeviceType($userAgent),
            'browser' => PageView::detectBrowser($userAgent),
            'user_id' => auth()->id(),
        ]);

        // Post view_count aktualisieren (async wenn möglich)
        if ($pageView->post_id) {
            Post::where('id', $pageView->post_id)->increment('view_count');
        }

        return response()->json(['status' => 'tracked'], 201);
    }

    /**
     * Holt Analytics Statistiken
     */
    public function stats(Request $request)
    {
        $period = $request->input('period', '7 days'); // 7 days, 30 days, 90 days
        $postId = $request->input('post_id');

        $query = PageView::query();

        if ($postId) {
            $query->where('post_id', $postId);
        }

        $query->where('viewed_at', '>=', now()->sub($period));

        // Gesamt Statistiken
        $totalViews = (clone $query)->count();
        $uniqueVisitors = (clone $query)->distinct('ip_address')->count();

        // Device Stats
        $deviceStats = (clone $query)
            ->select('device_type', DB::raw('count(*) as count'))
            ->groupBy('device_type')
            ->get()
            ->pluck('count', 'device_type');

        // Browser Stats
        $browserStats = (clone $query)
            ->select('browser', DB::raw('count(*) as count'))
            ->groupBy('browser')
            ->get()
            ->pluck('count', 'browser');

        // Top Posts
        $topPosts = Post::select('posts.*', DB::raw('COUNT(page_views.id) as views'))
            ->join('page_views', 'posts.id', '=', 'page_views.post_id')
            ->where('page_views.viewed_at', '>=', now()->sub($period))
            ->groupBy('posts.id')
            ->orderBy('views', 'desc')
            ->limit(10)
            ->get();

        // Views pro Tag (für Charts)
        $viewsPerDay = (clone $query)
            ->select(DB::raw('DATE(viewed_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'total_views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'devices' => $deviceStats,
            'browsers' => $browserStats,
            'top_posts' => $topPosts,
            'views_per_day' => $viewsPerDay,
        ]);
    }

    /**
     * Holt Analytics für einen spezifischen Post
     */
    public function postStats($postId)
    {
        $post = Post::with(['categories', 'tags'])->findOrFail($postId);

        $views = PageView::where('post_id', $postId);

        $stats = [
            'post' => $post,
            'total_views' => $views->count(),
            'unique_views' => $views->distinct('ip_address')->count(),
            'today_views' => $views->clone()->today()->count(),
            'week_views' => $views->clone()->thisWeek()->count(),
            'month_views' => $views->clone()->thisMonth()->count(),
        ];

        // Views über Zeit
        $stats['views_over_time'] = $views
            ->select(DB::raw('DATE(viewed_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();

        return response()->json($stats);
    }

    /**
     * Exportiert Analytics als CSV
     */
    public function export(Request $request)
    {
        $period = $request->input('period', '30 days');

        $views = PageView::with(['post', 'user'])
            ->where('viewed_at', '>=', now()->sub($period))
            ->orderBy('viewed_at', 'desc')
            ->get();

        $filename = 'analytics_' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($views) {
            $file = fopen('php://output', 'w');

            // Header
            fputcsv($file, [
                'ID',
                'Date',
                'Page URL',
                'Post Title',
                'Device',
                'Browser',
                'Country',
                'Referer'
            ]);

            // Rows
            foreach ($views as $view) {
                fputcsv($file, [
                    $view->id,
                    $view->viewed_at,
                    $view->page_url,
                    $view->post ? $view->post->title : '-',
                    $view->device_type,
                    $view->browser,
                    $view->country_code ?? '-',
                    $view->referer
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Anonymisiert IP-Adresse für DSGVO (letztes Oktett löschen)
     */
    protected function anonymizeIp(string $ip): string
    {
        // IPv4
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        // IPv6
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);
            $parts[count($parts) - 1] = '0';
            return implode(':', $parts);
        }

        return $ip;
    }
}
