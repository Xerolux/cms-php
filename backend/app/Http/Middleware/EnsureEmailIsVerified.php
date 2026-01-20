<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->hasVerifiedEmail()) {
            return response()->json([
                'error' => 'Email verification required',
                'message' => 'Your email address is not verified. Please check your email for a verification link.',
                'email_verified' => false
            ], 403);
        }

        return $next($request);
    }
}
