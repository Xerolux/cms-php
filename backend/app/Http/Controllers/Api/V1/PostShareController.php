<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostShare;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PostShareController extends Controller
{
    /**
     * Get share statistics for a post
     */
    public function getStats(Post $post): JsonResponse
    {
        $shares = $post->shares;

        $totalShares = $shares->sum('shares_count');
        $totalClicks = $shares->sum('clicks_count');

        $byPlatform = $shares->groupBy('platform')->map(function ($group) {
            return [
                'shares' => $group->sum('shares_count'),
                'clicks' => $group->sum('clicks_count'),
                'links' => $group->count(),
            ];
        });

        return response()->json([
            'total_shares' => $totalShares,
            'total_clicks' => $totalClicks,
            'by_platform' => $byPlatform,
            'recent_shares' => $shares->orderBy('created_at', 'desc')->take(10),
        ]);
    }

    /**
     * Generate share link for a platform
     */
    public function generateLink(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'platform' => 'required|in:facebook,twitter,linkedin,whatsapp,email',
        ]);

        $shareUrls = [
            'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($post->permalink),
            'twitter' => 'https://twitter.com/intent/tweet?url=' . urlencode($post->permalink) . '&text=' . urlencode($post->title),
            'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($post->permalink),
            'whatsapp' => 'https://wa.me/?text=' . urlencode($post->title . ' ' . $post->permalink),
            'email' => 'mailto:?subject=' . urlencode($post->title) . '&body=' . urlencode($post->permalink),
        ];

        // Create or update share record
        $share = PostShare::updateOrCreate(
            [
                'post_id' => $post->id,
                'platform' => $request->platform,
            ],
            [
                'share_url' => $shareUrls[$request->platform],
            ]
        );

        // Increment share count
        $share->increment('shares_count');

        return response()->json([
            'success' => true,
            'share_url' => $shareUrls[$request->platform],
            'share' => $share,
        ]);
    }

    /**
     * Track click on share link
     */
    public function trackClick(Request $request): JsonResponse
    {
        $request->validate([
            'share_id' => 'required|exists:post_shares,id',
        ]);

        $share = PostShare::find($request->share_id);
        $share->increment('clicks_count');

        return response()->json([
            'success' => true,
            'clicks_count' => $share->clicks_count,
        ]);
    }

    /**
     * Get QR code for post
     */
    public function getQrCode(Post $post): JsonResponse
    {
        $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($post->permalink);

        return response()->json([
            'success' => true,
            'qr_code_url' => $qrCodeUrl,
            'post_permalink' => $post->permalink,
        ]);
    }

    /**
     * Get all shares for a post
     */
    public function index(Post $post): JsonResponse
    {
        $shares = $post->shares()->orderBy('created_at', 'desc')->get();

        return response()->json($shares);
    }

    /**
     * Delete a share record
     */
    public function destroy(PostShare $share): JsonResponse
    {
        $this->authorize('delete', $share);

        $share->delete();

        return response()->json([
            'success' => true,
            'message' => 'Share record deleted',
        ]);
    }

    /**
     * Bulk share to multiple platforms
     */
    public function bulkShare(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'platforms' => 'required|array',
            'platforms.*' => 'in:facebook,twitter,linkedin,whatsapp,email',
        ]);

        $createdShares = [];

        foreach ($request->platforms as $platform) {
            $shareUrls = [
                'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode($post->permalink),
                'twitter' => 'https://twitter.com/intent/tweet?url=' . urlencode($post->permalink) . '&text=' . urlencode($post->title),
                'linkedin' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . urlencode($post->permalink),
                'whatsapp' => 'https://wa.me/?text=' . urlencode($post->title . ' ' . $post->permalink),
                'email' => 'mailto:?subject=' . urlencode($post->title) . '&body=' . urlencode($post->permalink),
            ];

            $share = PostShare::updateOrCreate(
                [
                    'post_id' => $post->id,
                    'platform' => $platform,
                ],
                [
                    'share_url' => $shareUrls[$platform],
                ]
            );

            $share->increment('shares_count');

            $createdShares[] = $share;
        }

        return response()->json([
            'success' => true,
            'shares' => $createdShares,
            'message' => count($createdShares) . ' share(s) created',
        ]);
    }
}
