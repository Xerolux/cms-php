<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SessionManagementService;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    protected SessionManagementService $sessionService;

    public function __construct(SessionManagementService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Get all active sessions for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $stats = $this->sessionService->getSessionStats($user);

        return response()->json($stats);
    }

    /**
     * Revoke a specific session
     */
    public function destroy(Request $request, string $tokenId)
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        // Prevent revoking current session via this endpoint
        if ($tokenId == $currentTokenId) {
            return response()->json([
                'error' => 'Cannot revoke current session. Use logout instead.'
            ], 400);
        }

        // Verify the session belongs to the user
        $session = \App\Models\UserSession::where('token_id', $tokenId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return response()->json([
                'error' => 'Session not found'
            ], 404);
        }

        $this->sessionService->revokeSession($tokenId);

        return response()->json([
            'message' => 'Session revoked successfully'
        ]);
    }

    /**
     * Revoke all sessions except the current one
     */
    public function destroyAll(Request $request)
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $count = $this->sessionService->revokeAllSessionsExceptCurrent($user, $currentTokenId);

        return response()->json([
            'message' => 'All other sessions have been revoked',
            'revoked_count' => $count
        ]);
    }

    /**
     * Update current session activity
     */
    public function heartbeat(Request $request)
    {
        $tokenId = $request->user()->currentAccessToken()->id;
        $this->sessionService->updateSessionActivity($tokenId);

        return response()->json([
            'message' => 'Session activity updated',
            'timestamp' => now()
        ]);
    }
}
