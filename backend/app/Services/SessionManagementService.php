<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\DB;

class SessionManagementService
{
    /**
     * Create a new session record
     */
    public function createSession(User $user, string $tokenId, string $deviceName = null): UserSession
    {
        $userAgent = request()->userAgent() ?? 'Unknown';
        $deviceInfo = $this->parseUserAgent($userAgent);

        return UserSession::create([
            'user_id' => $user->id,
            'token_id' => $tokenId,
            'device_name' => $deviceName ?? $deviceInfo['device'],
            'browser' => $deviceInfo['browser'],
            'platform' => $deviceInfo['platform'],
            'ip_address' => request()->ip(),
            'user_agent' => $userAgent,
            'last_activity_at' => now(),
            'expires_at' => config('session.lifetime') ? now()->addMinutes(config('session.lifetime')) : null,
        ]);
    }

    /**
     * Parse user agent string to extract device information
     */
    protected function parseUserAgent(string $userAgent): array
    {
        // Detect browser
        $browser = 'Unknown';
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches) && !str_contains($userAgent, 'Chrome')) {
            $browser = 'Safari ' . $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Edge ' . $matches[1];
        }

        // Detect platform
        $platform = 'Unknown';
        if (str_contains($userAgent, 'Windows NT 10.0')) {
            $platform = 'Windows 10';
        } elseif (str_contains($userAgent, 'Windows NT 6.3')) {
            $platform = 'Windows 8.1';
        } elseif (str_contains($userAgent, 'Windows')) {
            $platform = 'Windows';
        } elseif (str_contains($userAgent, 'Mac OS X')) {
            $platform = 'macOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            $platform = 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            $platform = 'Android';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $platform = 'iOS';
        }

        // Detect device
        $device = 'Desktop';
        if (str_contains($userAgent, 'Mobile') || str_contains($userAgent, 'Android')) {
            $device = 'Mobile';
        } elseif (str_contains($userAgent, 'Tablet') || str_contains($userAgent, 'iPad')) {
            $device = 'Tablet';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device' => $device,
        ];
    }

    /**
     * Update session activity
     */
    public function updateSessionActivity(string $tokenId): void
    {
        UserSession::where('token_id', $tokenId)->update([
            'last_activity_at' => now()
        ]);
    }

    /**
     * Get all active sessions for a user
     */
    public function getActiveSessions(User $user)
    {
        return UserSession::where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('last_activity_at', 'desc')
            ->get();
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(string $tokenId): bool
    {
        // Delete the Sanctum token
        DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->delete();

        // Delete the session record
        return UserSession::where('token_id', $tokenId)->delete() > 0;
    }

    /**
     * Revoke all sessions for a user except current
     */
    public function revokeAllSessionsExceptCurrent(User $user, string $currentTokenId): int
    {
        // Get all token IDs except current
        $tokenIds = UserSession::where('user_id', $user->id)
            ->where('token_id', '!=', $currentTokenId)
            ->pluck('token_id');

        // Delete all Sanctum tokens except current
        DB::table('personal_access_tokens')
            ->whereIn('id', $tokenIds)
            ->delete();

        // Delete all session records except current
        return UserSession::where('user_id', $user->id)
            ->where('token_id', '!=', $currentTokenId)
            ->delete();
    }

    /**
     * Clean up expired and inactive sessions
     */
    public function cleanupExpiredSessions(): int
    {
        $expiredSessions = UserSession::where(function ($query) {
            $query->where('expires_at', '<', now())
                ->orWhere('last_activity_at', '<', now()->subMinutes(30));
        })->get();

        $tokenIds = $expiredSessions->pluck('token_id');

        // Delete Sanctum tokens
        DB::table('personal_access_tokens')
            ->whereIn('id', $tokenIds)
            ->delete();

        // Delete session records
        return UserSession::whereIn('id', $expiredSessions->pluck('id'))->delete();
    }

    /**
     * Get session statistics for a user
     */
    public function getSessionStats(User $user): array
    {
        $sessions = $this->getActiveSessions($user);

        return [
            'total_active_sessions' => $sessions->count(),
            'sessions' => $sessions->map(function ($session) {
                return [
                    'id' => $session->id,
                    'token_id' => $session->token_id,
                    'device' => $session->device_name,
                    'browser' => $session->browser,
                    'platform' => $session->platform,
                    'ip_address' => $session->ip_address,
                    'last_activity' => $session->last_activity_at->diffForHumans(),
                    'last_activity_at' => $session->last_activity_at,
                    'is_current' => $session->token_id == request()->user()?->currentAccessToken()?->id,
                ];
            })
        ];
    }
}
