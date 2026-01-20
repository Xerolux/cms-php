<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorAuthenticatable
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Skip 2FA check if user is not authenticated (handled by auth middleware)
        if (!$user) {
            return $next($request);
        }

        // Skip 2FA check if user hasn't enabled 2FA
        if (!$user->two_factor_secret) {
            return $next($request);
        }

        // Allow access to 2FA verification routes
        $allowedRoutes = [
            '2fa.verify',
            '2fa.confirm',
            '2fa.disable',
            'auth.logout',
        ];

        if (in_array($request->route()?->getName(), $allowedRoutes)) {
            return $next($request);
        }

        // Check if 2FA session is confirmed
        if (session()->get('2fa.confirmed') !== true) {
            return response()->json([
                'message' => 'Two-factor authentication required',
                'requires_2fa' => true,
            ], 423);
        }

        return $next($request);
    }
}
